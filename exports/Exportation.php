<?php

class Exportation {

  public $name;
  public $dataSets = [];

  public function __construct($name)
  {
    $this->name = $name;
  }

  public function addDataset($name, $data)
  {
    $this->dataSets[$name] = $data;
  }

}