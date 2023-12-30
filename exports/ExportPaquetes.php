<?php
require_once __DIR__ .'/Exportable.php';
require_once __DIR__ .'/ExportAreas.php';

class ExportPaquetes implements Exportable {

  public $columMap;
  private $areas = null;
  private $model = null;

  public function __construct(Array $columMap = []) {
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
    $sql = "SELECT 
          l.codigo, l.nombre, c.nombre as contrato, t.nombre as tipo_area, cc.nombre as area, replace(l.tipo_enfasis, '&&', ', ') as enfasis
      from 
          listapaquete l join categoria_cargo cc on l.idcategoria = cc.idcategoria 
          join tipo_area t on cc.tipo_area_id = t.id
          join contratos c on l.contrato = c.codigo
      where 
          l.codigo_empresa = '{$_SESSION['empresa']}' order by l.nombre";

    $model = $this->getModel();

    $paquetes = $model->Execute($sql);

    $codigosPaquetes = array_map(fn($paq) => "'".$paq['codigo']."'", $paquetes);
    $codigosPaquetes = implode(",", $codigosPaquetes);
    
    $sql = "SELECT nompaquete as paquete, codproc as codigo_procedimiento, nomproc as nombre_procedimiento FROM paquete where codigopaq in ({$codigosPaquetes}) order by nompaquete, nomproc";
    $procedimientos = $model->Execute($sql);

    $data = [
      // Profesiogramas
      array_keys($this->columMap)[0] => $paquetes,

      // Procedimientos
      array_keys($this->columMap)[1] => $procedimientos
    ];

    return $data;
  }

  public function getMappedData() {

    $data = $this->getRawData();

    $dataMapped = [];

    foreach( $this->columMap as $name => $map ) {

      $dataMapped[$name] = [ array_values($map) ];

      $myData = $data[$name]; // Profesiogramas[] | Procedimientos[]

      foreach ($myData as $item) {
        $myItem = [];
        foreach (array_keys($map) as $key) {
          $myItem[] = $item[ $key ];
        }
        $dataMapped[$name][] = $myItem;
      }

    }
    
    return $dataMapped;
  }

  public function getExportation()
  {
    $data = $this->getMappedData();
    $exportation = new Exportation('Profesiogramas');
    foreach($data as $name => $value) {
      $exportation->addDataset($name, $value);
    }
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
  public function setAreaToItem($item)
  {
    if( empty( $this->areas ) ) {
      $this->areas = $this->getAreas();
    }

    $area = $this->findArea( $item->idcategoria, $this->areas );
    $item->area = $area->nombre;
    $item->tipo_area = $area->tipo_area;
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

  /**
   * Devuelve listado de areas
   * @return array
   */
  public function getAreas()
  {
    $areas = (new ExportAreas([]))->getRawData();
    return $areas;
  }

}