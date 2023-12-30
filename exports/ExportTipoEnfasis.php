<?php
require_once __DIR__ .'/Exportable.php';

class ExportTipoEnfasis implements Exportable {

  public $columMap;

  public function __construct(Array $columMap = []) {
    $this->setColumMap($columMap);
  }

  public function setModel($model)
  {

  }
  
  public function getModel()
  {
    
  }

  public function getRawData()
  {
    // Requerido para que el servicioTipoEnfasis.php encuentre el metodo que debe ejecutar
    $_REQUEST['accion'] = 'todos';
    $_REQUEST['condicion'] = "estado = 'on' order by nombre";

    // Limpiamos el buffe de salida de php para evitar corrupcion de datos
    ob_clean();
    ob_start();

    // Ejecutamos el servicio y obtenemos los datos
    include_once(__DIR__ . '/../servicioTipoEnfasis.php');
    return json_decode( ob_get_clean() );
  }

  public function getMappedData() {

    $data = $this->getRawData();
    
    $dataMapped = [ array_values($this->columMap) ];

    foreach ($data as $item) {
      $myItem = [];
      foreach ($this->columMap as $key => $value) {
        $myItem[$value] = $item->{ $key };
      }
      $dataMapped[] = $myItem;
    }
    
    return $dataMapped;
  }

  public function getExportation()
  {
    $data = $this->getMappedData();
    $exportation = new Exportation('Tipos de Enfasis');
    $exportation->addDataset('Tipos de Enfasis', $data);
    return $exportation;
  }

  public function setColumMap(Array $columMap) {
    $this->columMap = $columMap;
  }

}