<?php

/**
 * Interface que define los metodos requeridos por la clase ExportExcel para que una clase
 */
interface Exportable {

  public function setModel($model);
  public function getModel();


  /**
   * Permite obtener los datos en crudo de la base de datos
   * @return Array
   */
  public function getRawData();

  /**
   * Permite obtener los datos mapeados que luego seran inyectados al documento excel
   * @return Array
   */
  public function getMappedData();

  public function getExportation();

  /**
   * Permite establecer un arreglo asociativo cuyas llaves serviran para mapear los nombres de las columnas de los registros de la base de datos
   * @param Array $columMap
   * @return void
   */
  public function setColumMap(Array $columMap);

}