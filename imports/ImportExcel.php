<?php
require_once __DIR__ . '/ExcelReader.php';

class ImportExcel {

  private $model = null;
  private $importable;
  private $reader = null;
  private $readAsRaw = false;

  public function __construct(Importable $importable, $model = null)
  {
    $this->model = $model;
    $this->importable = $importable;
    $this->reader = $this->importable->getReader();
  }

  public function importData($model = null, $chunkSize = 100)
  {
    $data = $this->getData();

    $this->importable->setModel($model ?? $this->model);
    $this->importable->setInsertChunkSize($chunkSize);
    return $this->importable->insertRows($data);
    //print_r( json_encode($data) );
  }

  public function setReadAsRaw($read)
  {
    $this->readAsRaw = $read;
  }

  public function getReadAsRaw()
  {
    return $this->readAsRaw;
  }

  public function getData()
  {
    return $this->reader->getData( $this->readAsRaw );
  }

}