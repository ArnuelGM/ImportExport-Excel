<?php
require_once __DIR__ . '/Importable.php';
require_once __DIR__ . '/../exports/ExportExcel.php';
require_once __DIR__ . '/../exports/ExportTipoArea.php';

class ImportArea extends Importable {

  private $tipos_area = [];

  public function __construct($filePath = '')
  {
    $this->filePath = $filePath;
    $this->ignoreFirstRow = true;
    $this->tableName = 'categoria_cargo';
    $this->columMap = [
      'nombre' => 'NOMBRE', 
      'descripcion' => 'DESCRIPCIÓN',
      'tipo_area_id' => 'TIPO DE AREA'
    ];
  }

  public function insertRows($rows)
  {
    
    // Solo se inserta la primera hoja del excel
    $rows = $rows[0];
    $rows = array_filter($rows, fn($row) => !empty($row['tipo_area_id']));

    $set = [];
    $lastInserted = false;

    foreach ( $rows as $row ) {
      $row['tipo_area_id'] = $this->getTipoAreaId( $row['tipo_area_id'] );
      $row = $this->configRow($row);

      $columns = $this->getColumNames($row);
      $set[] = $this->getInsertRowSql($row);

      $lastInserted = false;

      if( count($set) <= $this->insertChunkSize ) {
        $values = implode(', ', $set);
        $this->execute("INSERT INTO [{$this->tableName}] {$columns} VALUES $values");
        $set = [];
        $lastInserted = true;
      }

    }

    if( ! $lastInserted ) {
      $values = implode(',', $set);
      $this->execute("INSERT INTO [{$this->tableName}] {$columns} VALUES $values");
    }
  }

  public function getColumNames($row)
  {
    $colums = array_keys($row);
    return "(" . implode(',', $colums) . ")";
  }

  private function configRow($row)
  {
    $row[ 'codigo_empresa' ] = $_SESSION['empresa'];
    $row[ 'coduser' ] = $_SESSION['codusuario'];
    $row[ 'nomuser' ] = $_SESSION['nomusuario'];
    $row[ 'fecha_creacion' ] = date('Y-m-d');
    $row[ 'estado' ] = "on";
    return $row;
  }

  public function execute($query)
  {
    return $this->model->Execute($query); 
  }

  private function getTipoAreaId($tipo_area)
  {
    // Si existe el tipo de area
    if( ! empty( $this->tipos_area[ $tipo_area ] ) ) return $this->tipos_area[ $tipo_area ];

    $model = $this->getModel();
    $sql = "SELECT id FROM tipo_area WHERE nombre = '{$tipo_area}' and empresa_id = '{$_SESSION['empresa']}'";
    $result = $model->Execute($sql);
    $this->tipos_area[$tipo_area] = $result[0]['id'];
    return $this->tipos_area[$tipo_area];
  }

  public function downloadTemplate($fileName = null)
  {
    $spreadSheet = $this->getSpreadSheetTemplate();
    $spreadSheet = $this->addTipoAreas($spreadSheet);
    $this->writeResponse($spreadSheet, 'Xlsx', $fileName);
  }

  private function addTipoAreas($spreadSheet)
  {
    $tipoAreaExport = new ExportTipoArea([
      'nombre' => 'NOMBRE TIPO DE AREA'
    ]);
    
    $areas = $tipoAreaExport->getMappedData();
    $tiposDeArea = $spreadSheet->createSheet();
    $tiposDeArea->fromArray($areas);
    $tiposDeArea->setTitle('Tipos de Area');
    $tiposDeArea->setSheetState('hidden');

    $lastRow = $tiposDeArea->getHighestDataRow('A');
    $formula1 = '\'Tipos de Area\'!$A$2:$A$'. $lastRow;

    $dataValidation = $this->getDataValidation($formula1);

    $spreadSheet->getSheet(0)->setDataValidation('C2:C101', $dataValidation);

    return $spreadSheet;
  }

}