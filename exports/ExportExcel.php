<?php
require_once __DIR__ .'/../../librerias/vendor/autoload.php';
require_once __DIR__ .'/Exportable.php';
require_once __DIR__ .'/Exportation.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Html;

class ExportExcel {

  private $exportable;
  private $spreadSheet = null;

  public function __construct(Exportable $exportable)
  {
    $this->setExportStrategy($exportable);
  }

  public function getRawData()
  {
    return $this->exportable->getRawData();
  }

  public function getMappedData()
  {
    return $this->exportable->getMappedData();
  }

  public function getExportation()
  {
    return $this->exportable->getExportation();
  }

  public function toExcel($fileName = 'Document')
  {
    $exportation = $this->getExportation();
    $spreadSheet = $this->buildDocument($exportation);
    $writer = new Xlsx($spreadSheet);
    
    $fileName = ($exportation->name ?? $fileName) . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
    $writer->save('php://output');
  }

  public function buildDocument($exportation = null)
  {
    $spreadSheet = $this->getSpreadSheet();
    $exportation = $exportation ?? $this->getExportation();
    foreach( $exportation->dataSets as $name => $dataset ) {
      $workSheet = $spreadSheet->createSheet();
      $workSheet->setTitle($name);
      $workSheet->fromArray($dataset);

      // Estableser ancho automatico y cabeceras con fuente en negrita
      foreach( $workSheet->getColumnIterator() as $colum) {
        $workSheet->getColumnDimension( $colum->getColumnIndex() )->setAutoSize(true);
        $workSheet->getStyle( $colum->getColumnIndex() . "1" )->getFont()->setBold(true);
      }
    }
    return $spreadSheet;
  }

  public function setSpreadSheet($spreadSheet)
  {
    $this->spreadSheet = $spreadSheet;
  }

  public function getSpreadSheet()
  {
    if( empty($this->spreadSheet) ) return $this->getDefaultSpreadSheet();
    return $this->spreadSheet;
  }

  public function getDefaultSpreadSheet() 
  {
    $this->spreadSheet = new Spreadsheet();
    $this->spreadSheet->removeSheetByIndex(0);
    return $this->spreadSheet;
  }

  public function setExportStrategy(Exportable $exportable) {
    $this->exportable = $exportable;
  }

}