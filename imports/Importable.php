<?php

require_once __DIR__ . '/../../librerias/vendor/autoload.php';
require_once __DIR__ . '/ExcelReader.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

abstract class Importable {

  public $fileName = 'Document';
  public $filePath;
  public $reader = null;
  public $columMap;
  public $ignoreFirstRow;
  public $model = null;
  public $insertChunkSize = 100;
  public $tableName;

  public abstract function insertRows($rows);

  public function getInsertRowSql($row)
  {
    return "(" . implode(',', $this->getInsertRowArray($row)) . ")";
  }

  public function getInsertRowArray($row)
  {
    $values = array_values($row);
    return array_map(fn ($val) => "'" . $val . "'" , $values);
  }

  public function setModel($model)
  {
    $this->model = $model;
  }

  public function getModel()
  {
    return $this->model;
  }

  public function setInsertChunkSize($chunnkSize)
  {
    $this->insertChunkSize = $chunnkSize;
  }

  public function getInsertChunckSize()
  {
    return $this->insertChunkSize;
  }

  public function setTableName($tableName)
  {
    $this->tableName = $tableName;
  }

  public function getTableName()
  {
    return $this->tableName;
  }

  public function getIgnoreFirstRow()
  {
    return $this->ignoreFirstRow;
  }

  public function setIgnoreFirstRow($ignore)
  {
    $this->ignoreFirstRow = $ignore;
  }

  public function getColumMap() {
    return $this->columMap;
  }

  public function setColumMap($columMap)
  {
    $this->columMap = $columMap;
  }

  public function setFilePath($filePath)
  {
    $this->filePath = $filePath;
  }

  public function getFilePath()
  {
    return $this->filePath;
  }

  public function buildReader()
  {
    $reader = new ExcelReader;
    $reader->setFilePath($this->filePath);
    $reader->setColumMap($this->columMap);
    $reader->setIgnoreFirstRow( $this->ignoreFirstRow );
    return $reader;
  }

  public function getReader()
  {
    return $this->reader = $this->buildReader();
  }

  public function downloadTemplate($fileName = '') 
  {
    $spreadSheet = $this->getSpreadSheetTemplate();
    $this->writeResponse($spreadSheet, 'Xlsx', $fileName);
  }
  
  public function writeResponse($spreadSheet, $writer = 'Xlsx', $fileName = '')
  {
    $writer = IOFactory::createWriter($spreadSheet, $writer);
    $writer = new $writer($spreadSheet);
  
    $fileName = (($fileName ?? $this->fileName) ?? 'Document') . '.xlsx';
  
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'. urlencode($fileName) .'"');
    $writer->save('php://output');
  }

  public function getSpreadSheetTemplate()
  {
    $spreadSheet = new SpreadSheet();
    $spreadSheet->removeSheetByIndex(0);
    
    //$workSheet = $spreadSheet->getActiveSheet();
    
    $values = array_values( $this->columMap );
    $esArray = is_array( $values[0] );

    if( $esArray ) {
      foreach( $this->columMap as $name => $ws ) {
        $workSheet = $spreadSheet->createSheet();
        $workSheet->setTitle($name);
        $workSheet->fromArray( array_values($ws) );
        $this->setWorkSheetColumnsAutosize($workSheet);
      }
    }
    else {
      $workSheet = $spreadSheet->createSheet();
      $workSheet->fromArray( array_values($this->columMap) );
      $this->setWorkSheetColumnsAutosize($workSheet);
    }

    return $spreadSheet;
  }

  public function getDataValidation($formula1, $errorTitle = '', $errorMessage = '', $promptTitle = '', $promptMessage = '')
  {
    $dataValidation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
    $dataValidation->setType( \PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST );
    $dataValidation->setErrorStyle( \PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP );
    $dataValidation->setAllowBlank(false);
    $dataValidation->setShowDropDown(true);
    $dataValidation->setShowInputMessage(true);
    $dataValidation->setShowErrorMessage(true);
    $dataValidation->setErrorTitle($errorTitle ?? 'Input error');
    $dataValidation->setError($errorMessage ?? 'Valor no permitido!');
    $dataValidation->setPromptTitle($promptTitle ?? 'Valores Permitidos');
    $dataValidation->setPrompt($promptMessage ?? 'Solo los valores en la lista son permitidos.');
    $dataValidation->setFormula1($formula1);
    return $dataValidation;
  }

  public function setWorkSheetColumnsAutosize($workSheet) 
  {
    foreach( $workSheet->getColumnIterator() as $colum) {
      $workSheet->getColumnDimension( $colum->getColumnIndex() )->setAutoSize(true);
      $workSheet->getStyle( $colum->getColumnIndex() . "1" )->getFont()->setBold(true);
    }
  }

}