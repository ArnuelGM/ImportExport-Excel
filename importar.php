<?php

require_once __DIR__ .'/imports/ImportArea.php';
require_once __DIR__ .'/imports/ImportExcel.php';
require_once __DIR__ .'/imports/ImportCargos.php';
require_once __DIR__ .'/imports/ImportPaquete.php';
require_once __DIR__ .'/imports/ImportTipoArea.php';

$import = $_GET['import'];
$model = new Model();

// IMPOTAR TIPOS DE AREA
if( $import == 'tipoArea' ) {

  if( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
    try {
      (new ImportTipoArea)->downloadTemplate('Template Import Tipo de Area');
      exit(0);
    }
    catch(Exception $error) {
      downloadTemplateError( $error->getMessage() );
    }
  }

  $document = $_FILES['document']['tmp_name'];
  if( empty($document) ) {
    documentNotFound( 'Archivo no encontrado.' );
  }

  try {
    $reader = new ImportTipoArea($document);
    
    $importer = new ImportExcel($reader, $model);
    $importer->importData($model);
    successImport('Tipos de Área Imported!!');
  }
  catch(Exception $error) {
    importError( $error->getMessage() );
  }
}


// IMPORTAR AREA
if( $import == 'area' ) {

  if( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
    try {
      (new ImportArea)->downloadTemplate('Template Import Area');
      exit(0);
    } catch (Exception $error) {
      downloadTemplateError( $error->getMessage() );
    }
  }

  $document = $_FILES['document']['tmp_name'];
  if( empty( $document ) ) {
    documentNotFound('Archivo no encontrado.');
  }

  try {
    $reader = new ImportArea($document);

    $importer = new ImportExcel($reader);
    $importer->importData($model);
    successImport('Areas Imported!!');
  } catch (Exception $error) {
    importError( $error->getMessage() );
  }
}


// IMPORTAR PAQUETES
if( $import == 'paquete' ) {

  if( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
    $importer = new ImportPaquete;
    $importer->setModel($model);
    $importer->downloadTemplate('Template Import Profesiograma');
    exit(0);
  }

  $document = $_FILES['document']['tmp_name'];
  if( empty($document) ) {
    documentNotFound();
  }

  try {
    $reader = new ImportPaquete($document);

    $importer = new ImportExcel($reader);
    $importer->setReadAsRaw(true);
    $data = $importer->importData($model);
    successImport($data);
  } catch (Exception $error) {
    importError( $error->getMessage() );
  }
}

// IMPORTAR CARGOS
if( $import == 'cargos' ) {

  if( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
    try {
      $importer = new ImportCargos;
      $importer->setModel($model);
      $importer->downloadTemplate('Template Import Cargos');
      exit(0);
    } catch (Exception $error) {
      downloadTemplateError($error->getMessage());
    }
  }

  $document = $_FILES['document']['tmp_name'];
  if( empty($document) ) {
    documentNotFound();
  }

  try {
    $reader = new ImportCargos($document);

    $importer = new ImportExcel($reader);
    $importer->importData($model);
    successImport('Cargos Imported!!');
  } catch (Exception $error) {
    importError( $error->getMessage() );
  }

}

function downloadTemplateError($message)
{
  print_r( json_encode([
    'error' => 'No fué posible obtener la plantilla de importación.',
    'message' => $message,
    'code' => 500
  ]));
  exit(0);
}

function documentNotFound($message = '')
{
  print_r( json_encode([
    'error' => 'Archivo no encontrado.',
    'message' => $message,
    'code' => 409
  ]));
  exit(0);
}

function importError($message = '')
{
  print_r( json_encode([
    'error' => 'Error al importar el documento, por favor intenta de nuevo.',
    'message' => $message,
    'code' => 500
  ]));
  exit(0);
}

function successImport($message = '')
{
  print_r( json_encode([
    'data' => 'Datos importados correctamente.',
    'message' => $message,
    'code' => 200
  ]));
  exit(0);
}