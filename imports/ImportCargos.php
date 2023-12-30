<?php

require_once __DIR__ . '/Importable.php';
require_once __DIR__ . '/../exports/ExportExcel.php';
require_once __DIR__ . '/../exports/ExportPaquetes.php';

class ImportCargos extends Importable {

  private $paquetes = [];

  public function __construct($filePath = '')
  {
    $this->filePath = $filePath;
    $this->ignoreFirstRow = true;
    $this->tableName = 'cargos';
    $this->columMap = [
      'nombre' => 'NOMBRE', 
      'paquete_id' => 'PROFESIOGRAMA :: (TIPO DE AREA - AREA)',
      'funciones_cargo' => "FUNCIONES DEL CARGO",
      'criterios_cargo' => "CRITERIOS DEL CARGO",
      'observaciones' => "OBSERVACIONES",
    ];
  }
  
  public function insertRows($rows)
  {

    // Solo se majeran los datos de la primera hoja del excel
    $rows = $rows[0];
    $rows = array_filter($rows, fn($row) => !empty($row['nombre']) && !empty($row['paquete_id']) );

    $set = [];
    $lastInserted = false;

    foreach ( $rows as $row ) {
      
      $nombre = implode(' - ', array_map(fn($word) => trim($word),explode('-', trim($row['nombre']))));
      $row['nombre'] = $nombre;

      $row = $this->setPaqueteAndArea( $row );
      $row = $this->configRow($row);

      $columns = $this->getColumNames($row);
      $set[] = $this->getInsertRowSql($row);

      $lastInserted = false;

      if( count($set) >= $this->insertChunkSize ) {
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

  public function execute($query)
  {
    return $this->model->Execute($query); 
  }

  public function getColumNames($row)
  {
    $colums = array_keys($row);
    return "(" . implode(',', $colums) . ")";
  }

  private function setRowData($row, $paquete)
  {
    $row['categoria_cargo_id'] = $paquete['idcategoria'];
    $row['paquete_id'] = $paquete['codigo'];
    return $row;
  }

  private function setPaqueteAndArea($row)
  {
    // Si existe el tipo de area
    if( ! empty( $this->paquetes[ $row['paquete_id'] ] ) ) {
      return $this->setRowData($row, $this->paquetes[ $row['paquete_id'] ]);
    }

    $nombrePaquete = $row['paquete_id'];

    $model = $this->getModel();
    $sql = "SELECT p.codigo, cc.idcategoria from 
    listaPaquete p join categoria_cargo cc on p.idcategoria = cc.idcategoria
    join tipo_area ta on cc.tipo_area_id = ta.id
    where p.codigo_empresa = '{$_SESSION['empresa']}' and p.contrato = '{$_SESSION['contrato']}' and
    cc.codigo_empresa = '{$_SESSION['empresa']}' and 
    ta.empresa_id = '{$_SESSION['empresa']}' and
    concat(p.nombre, ' :: (', ta.nombre,' - ', cc.nombre, ')') = '{$nombrePaquete}'";
    
    $result = $model->Execute($sql);
    $this->paquetes[ $row['paquete_id'] ] = $result[0];
    
    return $this->setRowData($row, $this->paquetes[ $row['paquete_id'] ]);
  }

  private function configRow($row)
  {
    $row[ 'empresa_id' ] = $_SESSION['empresa'];
    $row['contrato']     = $_SESSION['contrato'];
    $row[ 'usuario_nombre' ] = $_SESSION['nomusuario'];
    $row[ 'usuario_id' ] = $_SESSION['codusuario'];
    $row[ 'created_at' ] = date('Y-m-d');
    $row[ 'updated_at' ] = date('Y-m-d');
    return $row;
  }

  public function downloadTemplate($fileName = null)
  {
    $spreadSheet = $this->getSpreadSheetTemplate();
    $spreadSheet = $this->addPaquetes($spreadSheet);
    $this->writeResponse($spreadSheet, 'Xlsx', $fileName);
  }

  public function addPaquetes($spreadSheet)
  {
    $sql = "SELECT l.nombre, c.nombre as area, t.nombre as tipo_area from listapaquete l 
    join categoria_cargo c on l.idcategoria = c.idcategoria 
    join tipo_area t on c.tipo_area_id = t.id
    where l.codigo_empresa = '{$_SESSION['empresa']}' and l.contrato = '{$_SESSION['contrato']}' order by l.nombre";
    
    $paquetes = $this->getModel()->Execute($sql);
    $paquetes = array_map(fn($paquete) => [$paquete['nombre'] . " :: ({$paquete['tipo_area']} - {$paquete['area']})"], $paquetes);

    $paquetesWS = $spreadSheet->createSheet();
    $paquetesWS->fromArray($paquetes);
    $paquetesWS->setTitle('Paquetes');
    $paquetesWS->setSheetState('hidden');

    $lastRow = $paquetesWS->getHighestDataRow('A');
    $formula1 = '\'Paquetes\'!$A$1:$A$'. $lastRow;

    $dataValidation = $this->getDataValidation($formula1);

    $spreadSheet->getSheet(0)->setDataValidation('B2:B101', $dataValidation);

    return $spreadSheet;
  }

}