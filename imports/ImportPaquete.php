<?php
/**
 * Trabaja, trabaja fuerte y sin sesar, 
 * trabaja, que la frente justa que en sudor se moja,
 * jamas ante otra frente se sonrroja.
 * 
 * - Arnuel Gutierrez
 */

require_once __DIR__ . '/Importable.php';
require_once __DIR__ . '/../exports/ExportExcel.php';
require_once __DIR__ . '/../exports/ExportAreas.php';
require_once __DIR__ . '/../exports/ExportTipoArea.php';
require_once __DIR__ . '/../exports/ExportTipoEnfasis.php';


class ImportPaquete extends Importable {

  public $fileName = 'Template Paquetes';
  private $contratos = [];
  private $servicios = [];
  //private $categorias = [];

  public function __construct($filePath = '')
  {
    $this->filePath = $filePath;
    $this->ignoreFirstRow = false;
    $this->tableName = 'paquete';
    $this->columMap = [
      "Profesiogramas" => [
        'nombre'        => 'NOMBRE PROFESIOGRAMA',
        'contrato'      => 'NOMBRE DEL CONTRATO',
        'idcategoria'   => 'AREA (TIPO :: AREA)'
      ],
    ];
  }

  public function insertRows($sheetsData)
  {

    // Escogemos solo la primera hoja del excel
    $sheetData = $sheetsData[0];
    $headers = [];
    $profesiogramas = [];
    $data = [];
    foreach( $sheetData as $index => $row ) {

      // Set Headers
      if ( $index == 0 ) {
        $headers = $row;
        continue;
      }

      // Validamos que haya algun valor para el nombre del profesiograma
      // En caso de no tener, detenemos el procesamiento de mas profesiogramas
      if( empty(trim($row[0])) ) break;

      # Obtener datos para el profesiograma
      $nombre = implode(' - ', array_map(fn($word) => trim($word),explode('-', trim($row[0]))));
      $profesiograma['nompaquete'] = $nombre;
      $contrato = $this->getContrato(array('contrato' => $row[1]));
      $profesiograma['contrato'] = $contrato;
      $profesiograma['tipo_enfasis']      = $this->getEnfasis($headers, $row);
      $profesiograma['idcategoria']       = $this->getCategoria($row[2]);
      $procedimientos = $this->getProcedimientos($headers, $row);
      $profesiograma['procedimientos'] = $procedimientos;

      # Valida que el paquete no exista con el mismo nombre y contrato
      $paqueteExiste = $this->paqueteExiste($profesiograma, $contrato['codigo'], $contrato['empresa']);
      if( $paqueteExiste ) {
        $data['paquetes_not_imported'][] = [
          'paquete' => $profesiograma['nompaquete'],
          'errors' => [
            ['message' => 'Otro paquete ya existe con el mismo nombre en el contrato seleccionado.']
          ]
        ];
        continue;
      }

      # Valida que profesiograma tenga procedimientos
      if( count($procedimientos) == 0 ) {
        $data['paquetes_not_imported'][] = [
          'paquete' => $profesiograma['nompaquete'],
          'errors' => [
            ['message' => 'No se seleccionaron procedimientos para este profesiograma.']
          ]
        ];
        continue;
      }

      $profesiogramas[] = $profesiograma;
    }

    foreach ( $profesiogramas as $profesiograma ) {

      $contrato = $profesiograma['contrato'];
      $procedimientos = $profesiograma['procedimientos'];

      $rows = [];
      # Recorremos cada procedimiento para establecer sus propiedades
      foreach( $procedimientos as $proc ) {

        # Si algun procedimiento del profesiograma no tiene valor asignado
        # No se agregara el profesiograma
        $valor = $this->getValor($proc, $contrato);
        if( ! is_numeric($valor) || ( $valor <= 0 )  ) {
          $data['paquetes_not_imported'][] = [
            'paquete' => $profesiograma['nompaquete'],
            'errors' => [
              ['message' => "No existe precio configurado para el procedimiento [{$proc['nomproc']} :: {$proc['codproc']}]. No es posible agregarlo. Por favor comuníquese con el administrador del sistema."]
            ]
          ];
          continue 2; # Saltar a siguiente iteracion en buble forEach profesiogramas
        }

        $proc['nompaquete'] = $profesiograma['nompaquete'];
        $proc['cantidad'] = 1;
        $proc['tipo_enfasis'] = $profesiograma['tipo_enfasis'];
        $proc['contrato'] = $contrato['codigo'];
        $proc['manual'] = $contrato['manual'];
        $proc['cod_servicio'] = $this->getServicio($proc);
        // $proc['codigopaq'] = $profesiograma['codigopaq'];
        $proc['servicio'] = $proc['cod_servicio'];
        $proc['valor'] = $valor;
        $proc['idcategoria'] = $profesiograma['idcategoria'];
        $proc['tipo_evaluacion'] = '';
        $proc['cargo'] = '';

        $rows[] = $this->configRow($proc);
      }

      $set = [];

      # Obtenemos un nuevo codigo para el profesiograma actual
      $codigoPaq = $this->getCodigoPaquete();

      # Iteramos cada procedimiento para insertarlos en bloque
      foreach ( $rows as $row ) {
        $row['codigopaq'] = $codigoPaq;
        $columns = $this->getColumNames($row);
        $set[] = $this->getInsertRowSql($row);
      }

      $data = $this->insertData($set, $columns, $data, $profesiograma['nompaquete'], $rows);
    }
    # End forEach profesiogramas

    return $data;
  }

  private function insertData($set, $columns, $dataNotImported, $profesiograma, $procedimientos) {
    $values = implode(', ', $set);
    $sql = "INSERT INTO [{$this->tableName}] {$columns} VALUES $values";
    $inserted = $this->execute($sql);
    if( ! $inserted ) {
      $data = [
        'paquete' => $profesiograma,
        'errors' => sqlsrv_errors( SQLSRV_ERR_ERRORS ),
        'stmt' => $sql
      ];
      $dataNotImported['paquetes_not_imported'][] = $data;
    }
    else {
      $this->actualizarValores($procedimientos);
    }
    return $dataNotImported;
  }

  private function actualizarValores($procedimientos)
  {
    $head = "(codigo, tipo, idmanual, idpaquete, valor, codusuario, nomusuario, creacion, actualizacion)";
    $values = array_map(function ($proc) {
      return $this->getInsertRowSql([
        'codigo'        => $proc['codproc'],
        'tipo'          => 'SOAT',
        'idmanual'      => $proc['manual'],
        'idpaquete'     => $proc['codigopaq'],
        'valor'         => $proc['valor'],
        'codusuario'    => $proc['codigo_usuario'],
        'nomusuario'    => $proc['nom_usuario'],
        'creacion'      => $proc['creacion'],
        'actualizacion' => $proc['actualizacion'],
      ]);
    },$procedimientos);

    $sql = "INSERT into valores $head values " . implode(', ', $values);
    $modelo = $this->getModel();
    $result = $modelo->query($sql);
    return $result->numRows() > 0;
  } 

  private function paqueteExiste($profesiograma, $contrato, $empresa)
  {
    $sql = "SELECT count(*) as cantidad from paquete where 
      nompaquete = '{$profesiograma['nompaquete']}' and 
      codigo_empresa = '{$empresa}' and
      contrato = '{$contrato}'";
    $result = $this->getModel()->Execute($sql);
    return $result[0]['cantidad'] > 0;
  }

  private function getCodigoPaquete()
  {
    $sql = "SELECT valor from consecutivo where tabla = 'codigo_paquete'";
    $codigos = $this->getModel()->Execute($sql);
    $codigo = $codigos[0]['valor'];
    $update = $codigo + 1;
    $this->getModel()->Execute("UPDATE consecutivo set valor = '{$update}' where tabla = 'codigo_paquete'");
    return $codigo;
  }

  private function getContrato($profesiograma) 
  {
    if( !empty($this->contratos[$profesiograma['contrato']]) ) return $this->contratos[$profesiograma['contrato']];
    $sql = "SELECT * from contratos where empresa = '{$_SESSION['empresa']}' and nombre = '{$profesiograma['contrato']}'";
    $contratos = $this->getModel()->Execute($sql);
    $this->contratos[ $profesiograma['contrato'] ] = $contratos[0];
    return $contratos[0];
  }

  private function getEnfasis($headers, $row)
  {
    $separadorCount = 0;
    $enfasis = [];

    foreach($row as $index => $value) {

      if( $headers[$index] == 'T' ) $separadorCount++;
      if( $separadorCount < 1 ) continue;
      if( $separadorCount > 1 ) break;

      if( empty(trim($value)) || trim($value) == '0' ) continue;

      $enfasis[] = $headers[$index];
    }

    return implode('&&', $enfasis);
  }

  private function getCategoria($token)
  {    
    $sql = "SELECT cc.* 
      from categoria_cargo cc join tipo_area t on cc.tipo_area_id = t.id 
      where 
        cc.codigo_empresa = '{$_SESSION['empresa']}' and 
        t.empresa_id = '{$_SESSION['empresa']}' and
      concat(t.nombre, ' :: ', cc.nombre) = '{$token}'";

    $categorias = $this->getModel()->Execute($sql);
    
    return $categorias[0]['idcategoria'];
  }

  private function getProcedimientos($headers, $row)
  {
    $separadorCount = 0;
    $procedimientos = [];

    foreach($row as $index => $value) {

      if( $headers[$index] == 'T' ) $separadorCount++;
      if( $separadorCount < 2 ) continue;
      if( $separadorCount > 2 ) break;

      if( empty(trim($value)) || trim($value) == '0' ) continue;

      $tokens = explode(' :: ', $headers[$index]);

      $procedimientos[] = array( 'codproc' => $tokens[1], 'nomproc' => $tokens[0] );
    }

    return $procedimientos;
  }

  private function getServicio($procedimiento)
  {
    if( !empty($this->servicios[$procedimiento['codproc']]) ) return $this->servicios[$procedimiento['codproc']];
    $sql = "SELECT * from sis_tipo t join sis_proc p on t.fuente = p.fuente where p.codigo = '{$procedimiento['codproc']}'";
    $servcios = $this->getModel()->Execute($sql);
    $this->servicios[ $procedimiento['codproc'] ] = $servcios[0]['fuente'];
    return $servcios[0]['fuente'];
  }

  private function getValor($procedimiento, $contrato)
  {
    $sql = "SELECT * from valores where idmanual = '{$contrato['manual']}' and codigo = '{$procedimiento['codproc']}' and idpaquete = '0'";
    $precios = $this->getModel()->Execute($sql);
    return $precios[0]['valor'];
  }

  private function configRow($row)
  {
    $row[ 'codigo_empresa' ] = $_SESSION['empresa'];
    $row[ 'codigo_usuario' ] = $_SESSION['codusuario'];
    $row[ 'nom_usuario' ] = $_SESSION['nomusuario'];
    $row[ 'creacion' ] = date('Y-m-d');
    $row[ 'actualizacion' ] = date('Y-m-d');
    return $row;
  }

  public function getColumNames($row)
  {
    $colums = array_keys($row);
    return "(" . implode(',', $colums) . ")";
  }

  public function execute($query)
  {
    $result = $this->model->query($query);
    return $result->numRows() > 0;
  }

  public function downloadTemplate($fileName = null)
  {
    $spreadSheet = $this->getSpreadSheetTemplate();
    $spreadSheet = $this->addListaContratos($spreadSheet);
    $spreadSheet = $this->addListaAreas($spreadSheet);
    $spreadSheet = $this->addListaProcedimientos($spreadSheet);
    $spreadSheet = $this->addTipoEnfasisAndProcedimientosHeaders($spreadSheet);

    # Lista desplegable de los nombres de los profesiogramas en las hojas de enfasis y procedimientos
    // $spreadSheet = $this->configSheets($spreadSheet);

    $spreadSheet->setActiveSheetIndex(0);
    $this->writeResponse($spreadSheet, 'Xlsx', $fileName);
  }

  private function addListaContratos($spreadSheet)
  {
    $sql = "SELECT nombre from contratos where empresa = '{$_SESSION['empresa']}' and activo = '1' order by nombre";

    $contratos = $this->getModel()->Execute($sql);
    $contratos = array_map(fn($contrato) => [$contrato['nombre']], $contratos);

    $wsContratos = $spreadSheet->createSheet();
    $wsContratos->fromArray($contratos);
    $wsContratos->setTitle('Lista Contratos');
    $wsContratos->setSheetState('hidden');

    $lastRow = $wsContratos->getHighestDataRow('A');
    $formula1 = '\'Lista Contratos\'!$A$1:$A$'. $lastRow;

    $dataValidation = $this->getDataValidation($formula1);

    $spreadSheet->getSheet(0)->setDataValidation('B2:B101', $dataValidation);

    return $spreadSheet;
  }

  private function addListaAreas($spreadSheet)
  {
    $areasExport = new ExportAreas([
      'nombre' => 'NOMBRE',
      'tipo_area' => 'TIPO DE AREA',
    ]);
    $areasData = $areasExport->getRawData();
    $areasData = array_map(fn($area) => [$area->tipo_area. " :: " . $area->nombre], $areasData);
    
    $wsAreas = $spreadSheet->createSheet();
    $wsAreas->setTitle('Areas');
    $wsAreas->fromArray($areasData);
    $wsAreas->setSheetState('hidden');

    $lastRow = $wsAreas->getHighestDataRow('A');
    $formula1 = '\'Areas\'!$A$1:$A$'. $lastRow;

    $dataValidation = $this->getDataValidation($formula1);

    $spreadSheet->getSheet(0)->setDataValidation('C2:C101', $dataValidation);

    return $spreadSheet;
  }

  private function addTipoEnfasisAndProcedimientosHeaders($spreadSheet)
  {
    $sql = "SELECT * from tipo_enfasis where estado = 'on' order by nombre";

    $listaEnfasis = $this->getModel()->Execute($sql);
    $titulosEnfasis = [];

    foreach ($listaEnfasis as $enfasis) {
      $titulosEnfasis[] = $enfasis['nombre'];
    }

    $workSheet = $spreadSheet->getSheet(0);

    # Primer separador
    $workSheet->setCellValue('D1', 'T');

    $headerCount = 5;
    for ($i = 0; $i < count($titulosEnfasis); $i++) {
      $workSheet->setCellValueByColumnAndRow($headerCount, 1, $titulosEnfasis[$i]);
      $headerCount++;
    }

    # Segundo separador
    $workSheet->setCellValueByColumnAndRow($headerCount, 1, 'T');
    $headerCount++;

    # Set Autocompletes Procedimientos
    $listaProcedimientos = $spreadSheet->getSheetByName('Lista Procedimientos');
    $nombreLista = $listaProcedimientos->getTitle();
    $lastRow = $listaProcedimientos->getHighestDataRow('A');
    $formula1 = '\''.$nombreLista.'\'!$A$2:$A$'.$lastRow;
    $dataValidation = $this->getDataValidation($formula1);
    
    # Numero de columnas que tendran autocomple para procedimientos
    $autoCompleteNumbers = 20;

    for( $i = 0; $i < $autoCompleteNumbers; $i++ ) {
      $workSheet->getCellByColumnAndRow($headerCount, 1)->setDataValidation($dataValidation);
      $headerCount++;
    }

    # Tercer Separador
    $workSheet->setCellValueByColumnAndRow($headerCount, 1, 'T');
    $headerCount++;

    $this->setWorkSheetColumnsAutosize($workSheet);

    return $spreadSheet;
  }

  private function addListaProcedimientos($spreadSheet)
  {
    $sql = "SELECT DISTINCT p.* from sis_proc p join valores v on p.codigo = v.codigo join contratos c on v.idmanual = c.manual
                where c.empresa = '{$_SESSION['empresa']}' and v.idpaquete = '0' and p.enportal = '1' and p.estado = 'on' order by p.nombreve";

    $procedimientos = $this->getModel()->Execute($sql);

    # Cabeceras del excel
    $cabeceras = ['LISTA DE PROCEDIMIENTOS'];
    
    $procedimientos = array_map(fn($pro) => [$pro['nombreve'] . ' :: ' . $pro['codigo']], $procedimientos);

    $data = array_merge([$cabeceras], $procedimientos);

    $wsProcedimientos = $spreadSheet->createSheet();
    $wsProcedimientos->fromArray($data);
    $wsProcedimientos->setTitle('Lista Procedimientos');
    $wsProcedimientos->setSheetState('hidden');

    # Ancho automatico y tipo de letra en negrita para las cabeceras
    $this->setWorkSheetColumnsAutosize($wsProcedimientos);

    return $spreadSheet;
  }

  /* private function configSheets($spreadSheet)
  {
    $nameWs = $spreadSheet->getSheet(0)->getTitle();
    $formula1 = '\''.$nameWs.'\'!$A$2:$A$101';
    $dataValidation = $this->getDataValidation($formula1);

    $spreadSheet->getSheet(1)->setDataValidation('A2:A101', $dataValidation);
    $spreadSheet->getSheet(2)->setDataValidation('A2:A101', $dataValidation);
    return $spreadSheet;
  } */

}