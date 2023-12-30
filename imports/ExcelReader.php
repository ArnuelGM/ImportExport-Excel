<?php
require_once __DIR__ .'/../../librerias/vendor/autoload.php';
require_once __DIR__ . '/Importable.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ExcelReader {

  private $columMap = [];
  private $filePath = null;
  public $ignoreFirstRow = false;

  /**
   * Devuelve una matriz de arreglos mapeados con los nombre de las columnas y los valores
   *
   * @return Array
   */
  public function getData($raw_mode = false)
  {
    $spreadSheet = $this->getSpreadSheet();
    $rows = [];

    foreach( $spreadSheet->getWorksheetIterator() as $index => $workSheet ) {
      
      if( $raw_mode ) {
        $rows[] = $workSheet->toArray();
        continue;
      }

      if( is_array( array_values($this->columMap)[0] ) ) {
        $columMap = array_values($this->columMap)[$index];
        if( empty($columMap) ) break;

        $workSheetArray = array_slice($workSheet->toArray(), ($this->ignoreFirstRow ? 1 : 0)  );
        $rows[] = array_map( fn($row) => $this->mapRow($row, $columMap), $workSheetArray );
      }
      else {
        if( $index > 0 ) break;
        
        $workSheetArray = array_slice($workSheet->toArray(), ($this->ignoreFirstRow ? 1 : 0)  );
        $rows[] = array_map( fn ($row) => $this->mapRow($row, $this->columMap), $workSheetArray );
      }
    }
    $spreadSheet->disconnectWorksheets();
    unset($spreadSheet);
    return $rows;
  }

  private function mapRow($row, $columMap)
  {
    $myRow = [];
    foreach(array_keys($columMap) as $index => $value) {
      $myRow[$value] = $row[$index];
    }
    return $myRow;
  }

  public function getColumMap()
  {
    return $this->columMap;
  }

  public function setColumMap($columMap)
  {
    $this->columMap = $columMap;
  }

  public function getFilePath()
  {
    return $this->filePath;
  }

  public function setFilePath($path)
  {
    $this->filePath = $path;
  }

  public function setIgnoreFirstRow($ignore)
  {
    $this->ignoreFirstRow = $ignore;
  }

  /**
   * Devuelve el SpreadSheed con el contenido del archivo ubicado en $filePath
   *
   * @return SpreadSheet
   */
  public function getSpreadSheet()
  {
    return $this->readFile();
  }

  /**
   * Lee el archivo ubicado en $filePath
   *
   * @return SpreadSheet
   */
  private function readFile()
  {
    $reader = new Xlsx();
    return $reader->load( $this->filePath );
  }

}