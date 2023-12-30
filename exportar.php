<?php

require_once 'exports/ExportExcel.php';
require_once 'exports/ExportTipoArea.php';
require_once 'exports/ExportAreas.php';
require_once 'exports/ExportCargos.php';
require_once 'exports/ExportPaquetes.php';

$model = new Model();

$export = $_GET['export'];

// Exportar Tipo de Areas
if( $export == 'tiposArea' ) {

  $columMap = [
    'nombre' => 'TIPO DE ÁREA',
    'descripcion' => 'DESCRIPCIÓN'
  ];

  $exporter = new ExportExcel( new ExportTipoArea($columMap) );
  $exporter->toExcel();
  
}

// Exportar Areas
if( $export == 'areas' ) {

  $columMap = [
    'nombre' => 'NOMBRE DEL ÁREA',
    'tipo_area' => 'TIPO DE ÁREA',
    'descripcion' => 'DESCRIPCIÓN'
  ];

  $exporter = new ExportExcel( new ExportAreas($columMap) );
  $data = $exporter->toExcel();

}

// Exportar Profesiogramas
if( $export == 'paquetes' ) {
  $columMap = [
    'Profesiogramas' => [
      'nombre'    => 'NOMBRE DEL PROFESIOGRAMA',
      'contrato'  => 'NOMBRE DEL CONTRATO',
      'area'      => 'ÁREA',
      'tipo_area' => 'TIPO DE ÁREA',
      'enfasis'   => 'ÉNFASIS'
    ],
    'Procedimientos' => [
      'paquete' => 'NOMBRE DEL PROFESIOGRAMA',
      'codigo_procedimiento' => 'CÓDIGO DEL PROCEDIMIENTO',
      'nombre_procedimiento' => 'NOMBRE DEL PROCEDIMIENTO'
    ]
  ];

  $export = new ExportPaquetes($columMap);
  $export->setModel($model);
  $exporter = new ExportExcel( $export );
  $data = $exporter->toExcel();

}

// Exportar Cargos
if( $export == 'cargos' ) {

  $columMap = [
    'nombre'          => 'NOMBRE',
    'area'            => 'AREA',
    'tipo_area'       => 'TIPO DE AREA',
    'paquete'         => 'PAQUETE / PROFESIOGRAMA',
    'criterios_cargo' => 'CRITERIOS DE LIMITACION',
    'funciones_cargo' => 'FUNCIONES DEL CARGO',
    'observaciones'   => 'OBSERVACIONES'
  ];

  $export = new ExportCargos($columMap);
  $export->setModel($model);
  $exporter = new ExportExcel( $export );
  $data = $exporter->toExcel();

}

function showData($data) {
  echo "<code><pre>";
  print_r( json_encode($data, JSON_PRETTY_PRINT) );
  echo "</pre></code>";
}