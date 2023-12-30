<?php
require_once __DIR__ . '/Exportable.php';
require_once __DIR__ . '/ExportAreas.php';
require_once __DIR__ . '/ExportPaquetes.php';

class ExportCargos implements Exportable {

  public $columMap;
  private $areas = null;
  private $paquetes = null;
  private $model = null;

  public function __construct(Array $columMap = []) 
  {
    $this->setColumMap($columMap);
  }

  public function setModel($model)
  {
    $this->model = $model;
  }
  
  public function getModel()
  {
    return $this->model;
  }

  public function getRawData()
  {
    // Requerido para que el servicioCargos.php encuentre el metodo que debe ejecutar
    $_GET['execute'] = 'getAll';

    // Limpiamos el buffer de salida de php para evitar corrupcion de datos
    ob_clean();
    ob_start();

    // Ejecutamos el servicio y obtenemos los datos
    include_once(__DIR__ . '/../servicioCargos.php');
    return json_decode( ob_get_clean() );
  }

  public function getMappedData() {

    $data = $this->getRawData();

    $dataMapped = [ array_values($this->columMap) ];

    foreach ($data as $item) {
      $myItem = [];
      $item = $this->setArea($item);
      $item = $this->setPaquete($item);
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
    $exportation = new Exportation('Cargos');
    $exportation->addDataset('Cargos', $data);
    return $exportation;
  }

  public function setColumMap(Array $columMap) {
    $this->columMap = $columMap;
  }

  /**
   * Establece el area y tipo de area a un registro de tipo cargo
   * @param Object $item
   * @return void
   */
  public function setArea($item)
  {
    if( empty( $this->areas ) ) {
      $this->areas = $this->getAreas();
    }

    $area = $this->findArea( $item->categoria_cargo_id, $this->areas );
    if( $area ) {
      $item->area = $area->nombre;
      $item->tipo_area = $area->tipo_area;
    }
    return $item;
  }

  public function setPaquete($item)
  {
    if( empty( $this->paquetes ) ) {
      $this->paquetes = $this->getPaquetes();
    }
    $paquete = $this->findPaquete( $item->paquete_id, $this->paquetes );
    if( $paquete ) {
      $item->paquete = $paquete['nombre'];
    }
    return $item;
  }

  /**
   * Devuelve el area correspondiente al idcategoria pasado como parametro
   * @param Int $idcategoria
   * @param Array $areas
   * @return Object
   */
  public function findArea($idcategoria, $areas)
  {
    foreach ($areas as $area) {
      if( $area->idcategoria == $idcategoria ) return $area;
    }
    return null;
  }

  private function findPaquete($idpaquete, $paquetes)
  {
    foreach($paquetes as $paquete) {
      if( $paquete['codigo'] == $idpaquete ) return $paquete;
    }
    return null;
  }

  /**
   * Devuelve listado de areas
   * @return array
   */
  public function getAreas()
  {
    $areas = (new ExportAreas([]))->getRawData();
    return $areas;
  }

  public function getPaquetes()
  {
    $exporter = new ExportPaquetes([
      'Profesiogramas',
      'Procedimientos'
    ]);
    $exporter->setModel($this->getModel());
    $paquetes = $exporter->getRawData();
    return $paquetes[0];
  }

}