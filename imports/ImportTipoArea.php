<?php
require_once __DIR__ . '/Importable.php';

class ImportTipoArea extends Importable {

  public function __construct($filePath = '') 
  {
    $this->filePath = $filePath;
    $this->tableName = 'tipo_area';
    $this->ignoreFirstRow = true;
    $this->columMap = [
      'nombre' => 'NOMBRE',
      'descripcion' => 'DESCRIPCION'
    ];
  }

  public function insertRows($rows)
  {
    
    // Solo se insertará la primera hoja del excel
    $rows = $rows[0];
    
    $columns = $this->getColumNames();

    $set = [];
    $lastInserted = false;

    foreach ($rows as $row) {

      # Si una fila no tiene un nombre detenemos en esa fila el procesamiento de datos
      if( ! trim($row['nombre'])  ) {
        break;
      }
      
      $insertRowArray = $this->getInsertRowArray($row);
      $insertRowArray = $this->configInsert($insertRowArray);
      $set[] = "(" . implode(',', $insertRowArray) . ")";
      $lastInserted = false;

      if( count($set) >= $this->insertChunkSize ) {
        $values = implode(',', $set);
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

  public function getColumNames()
  {
    $colums = array_keys($this->columMap);
    $colums[] = 'empresa_id';
    $colums[] = 'usuario_id';
    $colums[] = 'usuario_nombre';
    $colums[] = 'created_at';
    $colums[] = 'updated_at';
    return "(" . implode(',', $colums) . ")";
  }

  public function configInsert($inserArray)
  {
    $inserArray[] = "'{$_SESSION['empresa']}'";
    $inserArray[] = "'{$_SESSION['codusuario']}'";
    $inserArray[] = "'{$_SESSION['nomusuario']}'";
    $inserArray[] = "'" . date('Y-m-d') . "'";
    $inserArray[] = "'" . date('Y-m-d') . "'";
    return $inserArray;
  }

  public function execute($query)
  {
    return $this->model->Execute($query); 
  }

}