<?php
session_start();
session_regenerate_id(true); // Prevent session fixation

// Redirect to login if not authenticated or not a customer
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
require_once 'db_connect.php';

     include 'header.php';
$design_data = null;
if (isset($_GET['load_id']) && isset($_GET['proposal_id']) && isset($_SESSION['email'])) {
    try {
        $stmt = $pdo->prepare('
            SELECT d.json_layout, d.svg_data 
            FROM designs d 
            JOIN proposals p ON d.proposal_id = p.id 
            JOIN users u ON p.users_id = u.id 
            WHERE d.id = ? AND d.proposal_id = ? AND u.email = ?
        ');
        $stmt->execute([$_GET['load_id'], $_GET['proposal_id'], $_SESSION['email']]);
        $design_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo 'Error loading design: ' . $e->getMessage();
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Floor Plan Designer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=deck" />
  <style>
    body,
    html {
      margin: 0;
      padding: 0;
      height: 100%;
      overflow: hidden;
      font-family: Arial, sans-serif;
    }

    .topbar {
      height: 60px;
      background-color: #f8f9fa;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 15px;
      border-bottom: 1px solid #ddd;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .left-controls .btn,
    .right-controls input[type="range"] {
      height: 36px;
    }

    .left-controls .btn {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .right-controls label {
      font-size: 14px;
      color: #333;
    }

    input[type="range"] {
      accent-color: #0d6efd;
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 60px;
      height: 100vh;
      background-color:#28a745;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 10px;
      z-index: 1000;
    }

    .sidebar button {
      background: none;
      border: none;
      color: white;
      margin: 10px 0;
      font-size: 20px;
      cursor: pointer;
      width: 100%;
    }

    .sidebar button:first-child {
      margin-top: 40px;
    }

    .sidebar button:hover {
      background-color: #495057;
    }

    .floating-panel {
      position: fixed;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      padding: 10px;
      display: none;
      z-index: 999;
      width: 250px;
      max-height: 600px;
      overflow-y: auto;
      opacity: 0;
      transform: translateX(-10px);
      transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .floating-panel.show {
      display: block;
      opacity: 1;
      transform: translateX(0);
    }

    .panel-item-group {
      margin-bottom: 10px;
    }

    .panel-item-toggle {
      font-weight: bold;
      background: #f0f0f0;
      padding: 6px;
      cursor: pointer;
      border-radius: 4px;
      font-size: 14px;
    }

    .panel-item-options {
      display: none;
      padding-left: 10px;
      border-left: 2px solid #ccc;
      margin-top: 5px;
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .panel-item-options .element-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 70px;
      text-align: center;
    }

    .panel-item-options img {
      width: 50px;
      height: 50px;
      cursor: grab;
      border: 1px solid #ccc;
      background: #fff;
      padding: 3px;
    }

    .panel-item-options .element-label {
      font-size: 12px;
      color: #333;
      margin-top: 3px;
      word-wrap: break-word;
    }

    .panel-item {
      padding: 8px;
      background: #f8f9fa;
      border: 1px solid #ccc;
      border-radius: 4px;
      margin-bottom: 6px;
      cursor: pointer;
      font-size: 14px;
    }

    .panel-item:hover {
      background: #e9ecef;
    }

    .main-canvas {
      margin-left: 80px;
      margin-top: 60px;
      height: calc(100% - 60px);
      width: calc(100% - 80px);
      display: flex;
      justify-content: center;
      align-items: center;
      background-color: #f5f5f5;
      position: relative;
    }

    .canvas-container {
      position: relative;
      display: inline-block;
    }

    #canvas {
      border: 2px dashed #ccc;
      background-color: white;
      cursor: move;
    }

    .ruler-top,
    .ruler-left {
      position: absolute;
      background-color: #e9ecef;
      overflow: hidden;
    }

    .ruler-top {
      top: -30px;
      left: 0;
      width: 800px;
      height: 30px;
    }

    .ruler-left {
      top: 0;
      left: -30px;
      width: 30px;
      height: 600px;
    }

    .canva-preview {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.95);
      z-index: 10;
      display: none;
    }

    .canva-preview.active {
      display: block;
    }

    .canva-preview iframe {
      width: 100%;
      height: 100%;
      border: none;
      border-radius: 8px;
    }

    .modal-content {
      max-height: 80vh;
      overflow-y: auto;
    }

    .text-edit-input {
      position: absolute;
      font-size: 14px;
      font-family: Arial, sans-serif;
      border: 1px solid #ccc;
      padding: 2px;
      background: white;
      z-index: 1000;
    }

    .search-results {
      margin-top: 10フラpx;
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .search-results .element-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 70px;
      text-align: center;
    }

    .search-results img {
      width: 50px;
      height: 50px;
      cursor: grab;
      border: 1px solid #ccc;
      background: #fff;
      padding: 3px;
    }

    .search-results .element-label {
      font-size: 12px;
      color: #333;
      margin-top: 3px;
      word-wrap: break-word;
    }

    .not-found {
      font-size: 14px;
      color: #888;
      margin-top: 10px;
    }

    .selection-box {
  stroke: black;
  stroke-width: 2;
  stroke-dasharray: 4; /* Dashed border */
  fill: none;
  display: none; /* Hidden by default */
}
.selection-box.selected {
  display: block; /* Visible when selected */
}
.resize-handle {
  fill: rgba(0, 0, 255, 0.5);
  stroke: black;
  stroke-width: 1;
  stroke-dasharray: 4; /* Dashed border */
  cursor: pointer;
  display: none;
}
.resize-handle.selected {
  display: block;
}
  
  </style>
</head>

<body>
  <div class="topbar">
   <div class="left-controls d-flex align-items-center gap-2">
  <div class="dropdown" style="margin-left: 75px;">
    <button class="btn btn-outline-primary dropdown-toggle" type="button" id="floorsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
      Floors
    </button>
    <ul class="dropdown-menu" aria-labelledby="floorsDropdown">
      <li><a class="dropdown-item" href="#" onclick="switchFloor('floor1')">Floor 1</a></li>
      <li><a class="dropdown-item" href="#" onclick="switchFloor('floor2')">Floor 2</a></li>
      <li><a class="dropdown-item" href="#" onclick="addFloor()">Add Floor</a></li>
    </ul>
  </div>
  <button class="btn btn-outline-secondary" onclick="undo()"><i class="fa-solid fa-arrow-left"></i></button>
  <button class="btn btn-outline-secondary" onclick="redo()"><i class="fa-solid fa-arrow-right"></i></button>
  <button class="btn btn-outline-secondary" id="rotateBtn"><i class="fa-solid fa-rotate"></i> Rotate</button>
  <button class="btn btn-outline-danger" id="deleteBtn"><i class="fa-solid fa-trash"></i> Delete</button>
  <button class="btn btn-outline-secondary" onclick="showDetails()"><i class="fa-solid fa-circle-info"></i> Details</button>
  <button class="btn btn-outline-secondary" onclick="showSettings()"><i class="fa-solid fa-gear"></i> Settings</button>
  <button class="btn btn-outline-primary" onclick="saveDesign()"><i class="fa-solid fa-save"></i> Save</button>
  <button class="btn btn-outline-success" onclick="downloadSVG()"><i class="fa-solid fa-download"></i> Download</button>
</div>
    <div class="right-controls d-flex align-items-center ms-auto">
      <label for="ruler" class="me-2 mb-0">Ruler:</label>
      <input type="range" min="1" max="100" value="10" id="ruler" oninput="updateRulersAndGrid()">
    </div>
  </div>

  <div class="main-canvas" id="mainCanvas">
    <div class="canvas-container">
      <svg class="ruler-top" width="800" height="30"></svg>
      <svg class="ruler-left" width="30" height="600"></svg>
      <svg id="canvas" width="800" height="600">
        <g id="grid"></g>
        <g id="floor1"></g>
      </svg>
      <div class="canva-preview" id="canvaPreview">
        <iframe src="https://baf26qnm3w8ksnk0.canva-hosted-embed.com/codelet/AAEAEGJhZjI2cW5tM3c4a3NuazAAAAAAAZaWv5INvW-H5hn752Nw-qzpsngp2T9dmuh5ASh2mrVZ2dDTe68/" sandbox="allow-same-origin allow-scripts allow-popups allow-forms" allowfullscreen></iframe>
      </div>
    </div>
  </div>

  <div class="sidebar">
    <button title="Search" onclick="togglePanel('search')"><i class="fa-solid fa-magnifying-glass"></i></button>
    <button title="Build" onclick="togglePanel('build')"><i class="fa-solid fa-building"></i></button>
    <button title="Outdoor" onclick="togglePanel('outdoor')"><span class="material-symbols-outlined">deck</span></button>
    <button title="Indoor" onclick="togglePanel('indoor')"><i class="fa-solid fa-couch"></i></button>
    <button title="Decorate" onclick="togglePanel('decorate')"><i class="fa-solid fa-tree"></i></button>
    <button title="Annotation" onclick="togglePanel('annotation')"><i class="fa-solid fa-pencil"></i></button>
    <button title="Preview Floor Plan" onclick="togglePreview()">👁</button>
  </div>

  <div class="floating-panel" id="panel-search">
    <input type="text" placeholder="Search..." style="width: 100%; padding: 8px; font-size: 14px;">
    <div class="search-results"></div>
  </div>

  <div class="floating-panel" id="panel-build">
    <div class="panel-item-group">
      <div class="panel-item-toggle">Door ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/singledoor.svg" draggable="true" class="element" data-type="singledoor">
          <span class="element-label">Single Door</span>
        </div>
        <div class="element-container">
          <img src="design/doubledoor.svg" draggable="true" class="element" data-type="doubledoor">
          <span class="element-label">Double Door</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Window ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/window2.svg" draggable="true" class="element" data-type="window2">
          <span class="element-label">Window</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Stairs ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/sidestair.svg" draggable="true" class="element" data-type="sidestair">
          <span class="element-label">Side Stair</span>
        </div>
        <div class="element-container">
          <img src="design/downstair.svg" draggable="true" class="element" data-type="downstair">
          <span class="element-label">Down Stair</span>
        </div>
        <div class="element-container">
          <img src="design/upstair.svg" draggable="true" class="element" data-type="upstair">
          <span class="element-label">Up Stair</span>
        </div>
        <div class="element-container">
          <img src="design/backyardstair.svg" draggable="true" class="element" data-type="backyardstair">
          <span class="element-label">Backyard Stair</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Wall ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/bar.svg" draggable="true" class="element" data-type="bar">
          <span class="element-label">Wall</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Room ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/room.svg" draggable="true" class="element" data-type="room">
          <span class="element-label">Room</span>
        </div>
      </div>
    </div>
  </div>

  <div class="floating-panel" id="panel-outdoor">
    <div class="panel-item-group">
      <div class="panel-item-toggle">Garden ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/bush1.svg" draggable="true" class="element" data-type="bush1">
          <span class="element-label">Bushes</span>
        </div>
        <div class="element-container">
          <img src="design/bush2.svg" draggable="true" class="element" data-type="bush2">
          <span class="element-label">Bushes</span>
        </div>
        <div class="element-container">
          <img src="design/bush3.svg" draggable="true" class="element" data-type="bush3">
          <span class="element-label">Bushes</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Pool ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/pool1.svg" draggable="true" class="element" data-type="pool1">
          <span class="element-label">Pool</span>
        </div>
        <div class="element-container">
          <img src="design/pool2.svg" draggable="true" class="element" data-type="pool2">
          <span class="element-label">Pool</span>
        </div>
        <div class="element-container">
          <img src="design/pool3.svg" draggable="true" class="element" data-type="pool3">
          <span class="element-label">Pool</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Patio ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/patio.svg" draggable="true" class="element" data-type="patio">
          <span class="element-label">Patio</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Bench ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/bench.svg" draggable="true" class="element" data-type="bench">
          <span class="element-label">Bench</span>
        </div>
      </div>
    </div>
  </div>

  <div class="floating-panel" id="panel-indoor">
    <div class="panel-item-group">
      <div class="panel-item-toggle">Sofa ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/sofa1.svg" draggable="true" class="element" data-type="sofa1">
          <span class="element-label">Single Sofa</span>
        </div>
        <div class="element-container">
          <img src="design/sofa2.svg" draggable="true" class="element" data-type="sofa2">
          <span class="element-label">Double Sofa</span>
        </div>
        <div class="element-container">
          <img src="design/sofa3.svg" draggable="true" class="element" data-type="sofa3">
          <span class="element-label">Triple Sofa</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Bed ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/doublebed.svg" draggable="true" class="element" data-type="doublebed">
          <span class="element-label">Double Bed</span>
        </div>
        <div class="element-container">
          <img src="design/singlebed.svg" draggable="true" class="element" data-type="singlebed">
          <span class="element-label">Single Bed</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Table ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/sidetable.svg" draggable="true" class="element" data-type="sidetable">
          <span class="element-label">Side Table</span>
        </div>
        <div class="element-container">
          <img src="design/diningtable2.svg" draggable="true" class="element" data-type="diningtable2">
          <span class="element-label">Table 4 persons</span>
        </div>
        <div class="element-container">
          <img src="design/diningtable1.svg" draggable="true" class="element" data-type="diningtable1">
          <span class="element-label">Table 6 persons</span>
        </div>
        <div class="element-container">
          <img src="design/centerroundtable.svg" draggable="true" class="element" data-type="centerroundtable">
          <span class="element-label">Center sofa Table</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Chair ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/chair.svg" draggable="true" class="element" data-type="chair">
          <span class="element-label">Chair</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Bathroom ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/kamot.svg" draggable="true" class="element" data-type="kamot">
          <span class="element-label">Kamot</span>
        </div>
        <div class="element-container">
          <img src="design/kamot2.svg" draggable="true" class="element" data-type="kamot2">
          <span class="element-label">Kamot with muslimshower</span>
        </div>
        <div class="element-container">
          <img src="design/bathtub1.svg" draggable="true" class="element" data-type="bathtub1">
          <span class="element-label">Bathtub</span>
        </div>
        <div class="element-container">
          <img src="design/bathtub2.svg" draggable="true" class="element" data-type="bathtub2">
          <span class="element-label">Bathtub</span>
        </div>
        <div class="element-container">
          <img src="design/washhandbasin.svg" draggable="true" class="element" data-type="washhandbasin">
          <span class="element-label">Wash Hand Basin</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Kitchen ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/kitchen1.svg" draggable="true" class="element" data-type="kitchen1">
          <span class="element-label">Kitchen</span>
        </div>
        <div class="element-container">
          <img src="design/stove.svg" draggable="true" class="element" data-type="stove">
          <span class="element-label">Stove</span>
        </div>
      </div>
    </div>
  </div>

  <div class="floating-panel" id="panel-decorate">
    <div class="panel-item-group">
      <div class="panel-item-toggle">Plant ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/plant.svg" draggable="true" class="element" data-type="plant">
          <span class="element-label">Plant</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Lamp ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/lamp.svg" draggable="true" class="element" data-type="lamp">
          <span class="element-label">Lamp</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Car ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/Car.svg" draggable="true" class="element" data-type="Car">
          <span class="element-label">Car</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Rug ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/rug.svg" draggable="true" class="element" data-type="rug">
          <span class="element-label">Rug</span>
        </div>
      </div>
    </div>
  </div>

  <div class="floating-panel" id="panel-annotation">
    <div class="panel-item-group">
      <div class="panel-item-toggle">Circle ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/circle.svg" draggable="true" class="element" data-type="circle">
          <span class="element-label">Circle</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Square ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/square.svg" draggable="true" class="element" data-type="square">
          <span class="element-label">Square</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Arrow ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="design/arrow.svg" draggable="true" class="element" data-type="arrow">
          <span class="element-label">Arrow</span>
        </div>
      </div>
    </div>
    <div class="panel-item-group">
      <div class="panel-item-toggle">Text ▼</div>
      <div class="panel-item-options">
        <div class="element-container">
          <img src="https://img.icons8.com/ios/50/text.png" draggable="true" class="element" data-type="text">
          <span class="element-label">Text</span>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="detailsModalLabel">Canvas Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Canvas Size: 800 x 600 pixels</p>
          <p>Elements: <span id="elementCount">0</span></p>
          <p>Ruler Scale: <span id="rulerScale">10</span> pixels/unit</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="settingsModalLabel">Settings</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="gridToggle" checked onchange="toggleGrid()">
            <label class="form-check-label" for="gridToggle">Show Grid</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="rulerToggle" checked onchange="toggleRulers()">
            <label class="form-check-label" for="rulerToggle">Show Rulers</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    
const svgCanvas = document.getElementById("canvas");
const deleteBtn = document.getElementById("deleteBtn");

let selectedElement = null;
let currentFloor = "floor1";
let isDragging = false;
let isResizing = false;
let dragStart = { x: 0, y: 0 };
let initialTranslate = { x: 0, y: 0 };
let floors = [{ id: "floor1", undoStack: [], redoStack: [], svgContent: "" }];
let activePanel = null;
let startX, startY, startWidth, startHeight, startTranslateX, startTranslateY, resizeHandleType;

const items = [
  { category: 'Door', src: 'design/singledoor.svg', type: 'singledoor', label: 'Single Door', panel: 'build' },
  { category: 'Door', src: 'design/doubledoor.svg', type: 'doubledoor', label: 'Double Door', panel: 'build' },
  { category: 'Window', src: 'design/window2.svg', type: 'window2', label: 'Window', panel: 'build' },
  { category: 'Stairs', src: 'design/sidestair.svg', type: 'sidestair', label: 'Side Stair', panel: 'build' },
  { category: 'Stairs', src: 'design/downstair.svg', type: 'downstair', label: 'Down Stair', panel: 'build' },
  { category: 'Stairs', src: 'design/upstair.svg', type: 'upstair', label: 'Up Stair', panel: 'build' },
  { category: 'Stairs', src: 'design/backyardstair.svg', type: 'backyardstair', label: 'Backyard Stair', panel: 'build' },
  { category: 'Wall', src: 'design/bar.svg', type: 'bar', label: 'Wall', panel: 'build' },
  { category: 'Room', src: 'design/room.svg', type: 'room', label: 'Room', panel: 'build' },
  { category: 'Garden', src: 'design/bush1.svg', type: 'bush1', label: 'Bushes', panel: 'outdoor' },
  { category: 'Garden', src: 'design/bush2.svg', type: 'bush2', label: 'Bushes', panel: 'outdoor' },
  { category: 'Garden', src: 'design/bush3.svg', type: 'bush3', label: 'Bushes', panel: 'outdoor' },
  { category: 'Pool', src: 'design/pool1.svg', type: 'pool1', label: 'Pool', panel: 'outdoor' },
  { category: 'Pool Gleam', src: 'design/pool2.svg', type: 'pool2', label: 'Pool', panel: 'outdoor' },
  { category: 'Pool', src: 'design/pool3.svg', type: 'pool3', label: 'Pool', panel: 'outdoor' },
  { category: 'Patio', src: 'design/patio.svg', type: 'patio', label: 'Patio', panel: 'outdoor' },
  { category: 'Bench', src: 'design/bench.svg', type: 'bench', label: 'Bench', panel: 'outdoor' },
  { category: 'Sofa', src: 'design/sofa1.svg', type: 'sofa1', label: 'Single Sofa', panel: 'indoor' },
  { category: 'Sofa', src: 'design/sofa2.svg', type: 'sofa2', label: 'Double Sofa', panel: 'indoor' },
  { category: 'Sofa', src: 'design/sofa3.svg', type: 'sofa3', label: 'Triple Sofa', panel: 'indoor' },
  { category: 'Bed', src: 'design/doublebed.svg', type: 'doublebed', label: 'Double Bed', panel: 'indoor' },
  { category: 'Bed', src: 'design/singlebed.svg', type: 'singlebed', label: 'Single Bed', panel: 'indoor' },
  { category: 'Table', src: 'design/sidetable.svg', type: 'sidetable', label: 'Side Table', panel: 'indoor' },
  { category: 'Table', src: 'design/diningtable2.svg', type: 'diningtable2', label: 'Table 4 persons', panel: 'indoor' },
  { category: 'Table', src: 'design/diningtable1.svg', type: 'diningtable1', label: 'Table 6 persons', panel: 'indoor' },
  { category: 'Table', src: 'design/centerroundtable.svg', type: 'centerroundtable', label: 'Center sofa Table', panel: 'indoor' },
  { category: 'Chair', src: 'design/chair.svg', type: 'chair', label: 'Chair', panel: 'indoor' },
  { category: 'Bathroom', src: 'design/kamot.svg', type: 'kamot', label: 'Kamot', panel: 'indoor' },
  { category: 'Bathroom', src: 'design/kamot2.svg', type: 'kamot2', label: 'Kamot with muslimshower', panel: 'indoor' },
  { category: 'Bathroom', src: 'design/bathtub1.svg', type: 'bathtub1', label: 'Bathtub', panel: 'indoor' },
  { category: 'Bathroom', src: 'design/bathtub2.svg', type: 'bathtub2', label: 'Bathtub', panel: 'indoor' },
  { category: 'Bathroom', src: 'design/washhandbasin.svg', type: 'washhandbasin', label: 'Wash Hand Basin', panel: 'indoor' },
  { category: 'Kitchen', src: 'design/kitchen1.svg', type: 'kitchen1', label: 'Kitchen', panel: 'indoor' },
  { category: 'Kitchen', src: 'design/stove.svg', type: 'stove', label: 'Stove', panel: 'indoor' },
  { category: 'Plant', src: 'design/plant.svg', type: 'plant', label: 'Plant', panel: 'decorate' },
  { category: 'Lamp', src: 'https://img.icons8.com/ios/50/lamp.png', type: 'lamp', label: 'Lamp', panel: 'decorate' },
  { category: 'Car', src: 'design/Car.svg', type: 'Car', label: 'Car', panel: 'decorate' },
  { category: 'Rug', src: 'design/rug.svg', type: 'rug', label: 'Rug', panel: 'decorate' },
  { category: 'Circle', src: 'design/circle.svg', type: 'circle', label: 'Circle', panel: 'annotation' },
  { category: 'Square', src: 'design/square.svg', type: 'square', label: 'Square', panel: 'annotation' },
  { category: 'Arrow', src: 'design/arrow.svg', type: 'arrow', label: 'Arrow', panel: 'annotation' },
  { category: 'Text', src: 'https://img.icons8.com/ios/50/text.png', type: 'text', label: 'Text', panel: 'annotation' }
];
// Initialize floors with loaded data
<?php if ($design_data): ?>
  floors = <?php echo $design_data['json_layout']; ?> || [{ id: "floor1", undoStack: [], redoStack: [], svgContent: "", rulerScale: 10, grid: true, rulers: true }];
  const loadedSvg = <?php echo json_encode($design_data['svg_data']); ?>;
  if (loadedSvg) {
    const parser = new DOMParser();
    const svgDoc = parser.parseFromString(loadedSvg, 'image/svg+xml').documentElement;
    svgCanvas.innerHTML = svgDoc.innerHTML;
  }
<?php else: ?>
  floors = [{ id: "floor1", undoStack: [], redoStack: [], svgContent: "", rulerScale: 10, grid: true, rulers: true }];
<?php endif; ?>

// Initialize drag elements
function initializeDragElements() {
  document.querySelectorAll(".element").forEach(el => {
    el.addEventListener("dragstart", (e) => {
      e.stopPropagation();
      e.dataTransfer.setData("text/plain", el.dataset.type);
      e.dataTransfer.effectAllowed = "copy";
      e.target.style.opacity = "0.5";
    });
    el.addEventListener("dragend", (e) => {
      e.target.style.opacity = "1";
    });
  });
}

// Drop element on SVG
svgCanvas.addEventListener("dragover", (e) => {
  e.preventDefault();
  e.dataTransfer.dropEffect = "copy";
});

svgCanvas.addEventListener("drop", (e) => {
  e.preventDefault();
  e.stopPropagation();
  const type = e.dataTransfer.getData("text/plain");
  if (!type) return;
  const pt = getSVGPoint(e);
  let newElement;
  if (type === "text") {
    newElement = createTextElement(pt.x, pt.y);
  } else {
    newElement = createSVGElement(type, pt.x - 25, pt.y - 25);
  }
  const floorGroup = svgCanvas.querySelector(`#${currentFloor}`);
  floorGroup.appendChild(newElement.group);
  const currentFloorData = floors.find(f => f.id === currentFloor);
  currentFloorData.undoStack.push({ element: newElement, floor: currentFloor });
  currentFloorData.redoStack = [];
  selectElement(newElement);
  updateDetails();
});

// Convert screen coordinates to SVG
function getSVGPoint(event) {
  const pt = svgCanvas.createSVGPoint();
  pt.x = event.clientX;
  pt.y = event.clientY;
  return pt.matrixTransform(svgCanvas.getScreenCTM().inverse());
}

// Create new image element with selection box and 8 resize handles
function createSVGElement(type, x, y) {
  const group = document.createElementNS("http://www.w3.org/2000/svg", "g");
  group.setAttribute("class", "draggable-group");

  const elem = document.createElementNS("http://www.w3.org/2000/svg", "image");
  const item = items.find(i => i.type === type);
  elem.setAttribute("href", item ? item.src : `design/${type}.svg`);
  elem.setAttribute("x", "0");
  elem.setAttribute("y", "0");
  elem.setAttribute("width", "50");
  elem.setAttribute("height", "50");
  elem.setAttribute("class", "draggable");
  elem.setAttribute("data-type", type);
  elem.setAttribute("data-angle", "0");

  // Create selection box
  const selectionBox = document.createElementNS("http://www.w3.org/2000/svg", "rect");
  selectionBox.setAttribute("x", "-2");
  selectionBox.setAttribute("y", "-2");
  selectionBox.setAttribute("width", "54");
  selectionBox.setAttribute("height", "54");
  selectionBox.setAttribute("class", "selection-box");

  // Create 8 resize handles
  const resizeHandles = [
    { type: "top", x: 20, y: -5, cursor: "n-resize" },
    { type: "bottom", x: 20, y: 55, cursor: "s-resize" },
    { type: "left", x: -5, y: 20, cursor: "w-resize" },
    { type: "right", x: 55, y: 20, cursor: "e-resize" },
    { type: "top-left", x: -5, y: -5, cursor: "nw-resize" },
    { type: "top-right", x: 55, y: -5, cursor: "ne-resize" },
    { type: "bottom-left", x: -5, y: 55, cursor: "sw-resize" },
    { type: "bottom-right", x: 55, y: 55, cursor: "se-resize" }
  ].map(handle => {
    const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
    rect.setAttribute("x", handle.x);
    rect.setAttribute("y", handle.y);
    rect.setAttribute("width", "10");
    rect.setAttribute("height", "10");
    rect.setAttribute("class", "resize-handle");
    rect.setAttribute("data-handle", handle.type);
    rect.style.cursor = handle.cursor;
    rect.addEventListener("mousedown", (e) => {
      e.preventDefault();
      e.stopPropagation();
      isResizing = true;
      selectedElement = elem;
      startX = getSVGPoint(e).x;
      startY = getSVGPoint(e).y;
      startWidth = parseFloat(elem.getAttribute("width"));
      startHeight = parseFloat(elem.getAttribute("height"));
      // Initialize startTranslateX/Y from current transform
      const group = elem.parentNode;
      const transform = group.getAttribute("transform") || "translate(0, 0)";
      const translateMatch = transform.match(/translate\((-?\d+\.?\d*),\s*(-?\d+\.?\d*)\)/);
      startTranslateX = translateMatch ? parseFloat(translateMatch[1]) : 0;
      startTranslateY = translateMatch ? parseFloat(translateMatch[2]) : 0;
      resizeHandleType = handle.type;
    });
    return rect;
  });

  group.appendChild(elem);
  group.appendChild(selectionBox);
  resizeHandles.forEach(handle => group.appendChild(handle));
  group.setAttribute("transform", `translate(${x}, ${y})`);

  elem.addEventListener("mousedown", (e) => {
    e.preventDefault();
    e.stopPropagation();
    selectElement({ main: elem, group, selectionBox, resizeHandles });
    startDrag(e, elem);
  });

  return { main: elem, group, selectionBox, resizeHandles };
}

// Create new text element
function createTextElement(x, y) {
  const textElem = document.createElementNS("http://www.w3.org/2000/svg", "text");
  textElem.setAttribute("x", x);
  textElem.setAttribute("y", y);
  textElem.setAttribute("font-size", "14");
  textElem.setAttribute("font-family", "Arial, sans-serif");
  textElem.setAttribute("fill", "black");
  textElem.setAttribute("class", "draggable");
  textElem.setAttribute("data-type", "text");
  textElem.setAttribute("data-angle", "0");
  textElem.textContent = "Text";

  textElem.addEventListener("mousedown", (e) => {
    e.preventDefault();
    e.stopPropagation();
    selectElement({ main: textElem, group: textElem, selectionBox: null, resizeHandles: null });
    startDrag(e, textElem);
  });

  textElem.addEventListener("dblclick", (e) => {
    editTextElement(textElem, e);
  });

  return { main: textElem, group: textElem, selectionBox: null, resizeHandles: null };
}

// Edit text element
function editTextElement(textElem, event) {
  const bbox = textElem.getBBox();
  const pt = getSVGPoint(event);
  const input = document.createElement("input");
  input.type = "text";
  input.className = "text-edit-input";
  input.value = textElem.textContent;
  input.style.left = `${event.clientX}px`;
  input.style.top = `${event.clientY}px`;
  input.style.width = `${bbox.width + 10}px`;
  document.body.appendChild(input);
  input.focus();

  input.addEventListener("blur", () => {
    textElem.textContent = input.value || "Text";
    document.body.removeChild(input);
    const currentFloorData = floors.find(f => f.id === currentFloor);
    const index = currentFloorData.undoStack.findIndex(item => item.element.main === textElem && item.floor === currentFloor);
    if (index !== -1) {
      currentFloorData.undoStack[index] = { element: { main: textElem, group: textElem, selectionBox: null, resizeHandles: null }, floor: currentFloor };
    } else {
      currentFloorData.undoStack.push({ element: { main: textElem, group: textElem, selectionBox: null, resizeHandles: null }, floor: currentFloor });
    }
    currentFloorData.redoStack = [];
  });

  input.addEventListener("keypress", (e) => {
    if (e.key === "Enter") input.blur();
  });
}

// Select and show selection box and resize handles
function selectElement(elemData) {
  clearSelection();
  selectedElement = elemData.main;
  if (elemData.selectionBox) {
    elemData.selectionBox.classList.add("selected");
  }
  if (elemData.resizeHandles) {
    elemData.resizeHandles.forEach(handle => handle.classList.add("selected"));
    const floorGroup = svgCanvas.querySelector(`#${currentFloor}`);
    if (!floorGroup.contains(elemData.group)) {
      floorGroup.appendChild(elemData.group);
    }
  }
  if (elemData.main.getAttribute("data-type") === "text") {
    elemData.main.addEventListener("dblclick", (e) => {
      editTextElement(elemData.main, e);
    });
  }
}

// Clear selected element
function clearSelection() {
  if (selectedElement) {
    const group = selectedElement.parentNode;
    const selectionBox = group.querySelector(".selection-box");
    if (selectionBox) {
      selectionBox.classList.remove("selected");
    }
    const resizeHandles = group.querySelectorAll(".resize-handle");
    resizeHandles.forEach(handle => handle.classList.remove("selected"));
    selectedElement = null;
  }
}

// Start dragging
function startDrag(e, elem) {
  isDragging = true;
  selectedElement = elem;
  dragStart = getSVGPoint(e);
  
  // Parse current transform to get initial translate values
  const group = elem.parentNode;
  const transform = group.getAttribute("transform") || "translate(0, 0)";
  const translateMatch = transform.match(/translate\((-?\d+\.?\d*),\s*(-?\d+\.?\d*)\)/);
  initialTranslate = translateMatch 
    ? { x: parseFloat(translateMatch[1]), y: parseFloat(translateMatch[2]) }
    : { x: 0, y: 0 };
  
  svgCanvas.addEventListener("mousemove", onDrag);
  svgCanvas.addEventListener("mouseup", endDrag);
}

// Handle dragging
function onDrag(e) {
  if (!isDragging || !selectedElement) return;

  const pt = getSVGPoint(e);
  const dx = pt.x - dragStart.x + initialTranslate.x;
  const dy = pt.y - dragStart.y + initialTranslate.y;

  const angle = parseInt(selectedElement.getAttribute("data-angle") || "0", 10);
  const group = selectedElement.parentNode;
  const width = parseFloat(selectedElement.getAttribute("width"));
  const height = parseFloat(selectedElement.getAttribute("height"));
  const centerX = width / 2;
  const centerY = height / 2;

  group.setAttribute("transform", `translate(${dx}, ${dy}) rotate(${angle} ${centerX} ${centerY})`);
}

// End dragging
function endDrag() {
  if (isDragging && selectedElement) {
    const currentFloorData = floors.find(f => f.id === currentFloor);
    const index = currentFloorData.undoStack.findIndex(item => item.element.main === selectedElement && item.floor === currentFloor);
    const group = selectedElement.parentNode;
    const selectionBox = group.querySelector(".selection-box");
    const resizeHandles = group.querySelectorAll(".resize-handle");
    const update = { element: { main: selectedElement, group, selectionBox, resizeHandles }, floor: currentFloor };
    if (index !== -1) {
      currentFloorData.undoStack[index] = update;
    } else {
      currentFloorData.undoStack.push(update);
    }
    currentFloorData.redoStack = [];
  }
  isDragging = false;
  svgCanvas.removeEventListener("mousemove", onDrag);
  svgCanvas.removeEventListener("mouseup", endDrag);
}

// Handle resize move
document.addEventListener("mousemove", (e) => {
  if (!selectedElement || !isResizing) return;
  e.preventDefault();
  const pt = getSVGPoint(e);

  const currentX = pt.x;
  const currentY = pt.y;
  let newWidth = startWidth;
  let newHeight = startHeight;
  let newTranslateX = startTranslateX;
  let newTranslateY = startTranslateY;

  switch (resizeHandleType) {
    case "top":
      newHeight = Math.max(10, startHeight - (currentY - startY));
      newTranslateY = startTranslateY + (currentY - startY);
      break;
    case "bottom":
      newHeight = Math.max(10, startHeight + (currentY - startY));
      break;
    case "left":
      newWidth = Math.max(10, startWidth - (currentX - startX));
      newTranslateX = startTranslateX + (currentX - startX);
      break;
    case "right":
      newWidth = Math.max(10, startWidth + (currentX - startX));
      break;
    case "top-left":
      newWidth = Math.max(10, startWidth - (currentX - startX));
      newHeight = Math.max(10, startHeight - (currentY - startY));
      newTranslateX = startTranslateX + (currentX - startX);
      newTranslateY = startTranslateY + (currentY - startY);
      break;
    case "top-right":
      newWidth = Math.max(10, startWidth + (currentX - startX));
      newHeight = Math.max(10, startHeight - (currentY - startY));
      newTranslateY = startTranslateY + (currentY - startY);
      break;
    case "bottom-left":
      newWidth = Math.max(10, startWidth - (currentX - startX));
      newHeight = Math.max(10, startHeight + (currentY - startY));
      newTranslateX = startTranslateX + (currentX - startX);
      break;
    case "bottom-right":
      newWidth = Math.max(10, startWidth + (currentX - startX));
      newHeight = Math.max(10, startHeight + (currentY - startY));
      break;
  }

  selectedElement.setAttribute("width", newWidth);
  selectedElement.setAttribute("height", newHeight);

  const group = selectedElement.parentNode;
  const angle = parseInt(selectedElement.getAttribute("data-angle") || "0", 10);
  const centerX = newWidth / 2;
  const centerY = newHeight / 2;
  group.setAttribute("transform", `translate(${newTranslateX}, ${newTranslateY}) rotate(${angle} ${centerX} ${centerY})`);

  // Update selection box
  const selectionBox = group.querySelector(".selection-box");
  if (selectionBox) {
    selectionBox.setAttribute("x", -2);
    selectionBox.setAttribute("y", -2);
    selectionBox.setAttribute("width", newWidth + 4);
    selectionBox.setAttribute("height", newHeight + 4);
  }

  // Update resize handle positions
  const handles = group.querySelectorAll(".resize-handle");
  handles.forEach(handle => {
    const type = handle.getAttribute("data-handle");
    const positions = {
      top: { x: newWidth / 2 - 5, y: -5 },
      bottom: { x: newWidth / 2 - 5, y: newHeight },
      left: { x: -5, y: newHeight / 2 - 5 },
      right: { x: newWidth, y: newHeight / 2 - 5 },
      "top-left": { x: -5, y: -5 },
      "top-right": { x: newWidth, y: -5 },
      "bottom-left": { x: -5, y: newHeight },
      "bottom-right": { x: newWidth, y: newHeight }
    };
    handle.setAttribute("x", positions[type].x);
    handle.setAttribute("y", positions[type].y);
  });
});

// End resize
document.addEventListener("mouseup", () => {
  if (isResizing && selectedElement) {
    const currentFloorData = floors.find(f => f.id === currentFloor);
    const index = currentFloorData.undoStack.findIndex(item => item.element.main === selectedElement && item.floor === currentFloor);
    const group = selectedElement.parentNode;
    const selectionBox = group.querySelector(".selection-box");
    const resizeHandles = group.querySelectorAll(".resize-handle");
    const update = { element: { main: selectedElement, group, selectionBox, resizeHandles }, floor: currentFloor };
    if (index !== -1) {
      currentFloorData.undoStack[index] = update;
    } else {
      currentFloorData.undoStack.push(update);
    }
    currentFloorData.redoStack = [];
  }
  isResizing = false;
});

// Deselect when clicking blank space
svgCanvas.addEventListener("mousedown", (e) => {
  if (!e.target.classList.contains("draggable") && !e.target.classList.contains("resize-handle")) {
    clearSelection();
  }
});

// Rotate selected element
document.getElementById("rotateBtn").addEventListener("click", () => {
  if (!selectedElement) return;
  let angle = parseInt(selectedElement.getAttribute("data-angle") || "0", 10);
  angle = (angle + 45) % 360;
  selectedElement.setAttribute("data-angle", angle);

  const group = selectedElement.parentNode;
  const width = parseFloat(selectedElement.getAttribute("width"));
  const height = parseFloat(selectedElement.getAttribute("height"));
  const centerX = width / 2;
  const centerY = height / 2;

  const currentTransform = group.getAttribute("transform") || "";
  const translateMatch = currentTransform.match(/translate\(([^)]+)\)/);
  const translate = translateMatch ? translateMatch[0] : "translate(0, 0)";
  group.setAttribute("transform", `${translate} rotate(${angle} ${centerX} ${centerY})`);

  const currentFloorData = floors.find(f => f.id === currentFloor);
  const index = currentFloorData.undoStack.findIndex(item => item.element.main === selectedElement && item.floor === currentFloor);
  if (index !== -1) {
    currentFloorData.undoStack[index] = { element: { main: selectedElement, group, selectionBox: group.querySelector(".selection-box"), resizeHandles: group.querySelectorAll(".resize-handle") }, floor: currentFloor };
  } else {
    currentFloorData.undoStack.push({ element: { main: selectedElement, group, selectionBox: group.querySelector(".selection-box"), resizeHandles: group.querySelectorAll(".resize-handle") }, floor: currentFloor });
  }
  currentFloorData.redoStack = [];
});

// Delete selected element
document.getElementById("deleteBtn").addEventListener("click", () => {
  if (selectedElement) {
    const currentFloorData = floors.find(f => f.id === currentFloor);
    const index = currentFloorData.undoStack.findIndex(item => item.element.main === selectedElement && item.floor === currentFloor);
    if (index !== -1) {
      currentFloorData.redoStack.push(currentFloorData.undoStack[index]);
      currentFloorData.undoStack.splice(index, 1);
    }
    selectedElement.parentNode.remove();
    selectedElement = null;
    updateDetails();
  }
});

function togglePreview() {
  const preview = document.getElementById('canvaPreview');
  preview.classList.toggle('active');
}

function switchFloor(floorId) {
  // Save the current floor's SVG content
  const currentFloorData = floors.find(f => f.id === currentFloor);
  const currentFloorGroup = svgCanvas.querySelector(`#${currentFloor}`);
  if (currentFloorGroup) {
    currentFloorData.svgContent = currentFloorGroup.outerHTML;
    currentFloorData.undoStack = currentFloorData.undoStack.filter(item => item.floor === currentFloor);
    currentFloorData.redoStack = currentFloorData.redoStack || [];
  }

  // Switch to the new floor
  currentFloor = floorId;
  let targetFloorData = floors.find(f => f.id === floorId);
  if (!targetFloorData) {
    targetFloorData = { id: floorId, undoStack: [], redoStack: [], svgContent: "" };
    floors.push(targetFloorData);
  }

  // Clear the SVG canvas
  svgCanvas.innerHTML = '';
  // Add grid
  svgCanvas.appendChild(document.createElementNS("http://www.w3.org/2000/svg", "g")).setAttribute("id", "grid");
  // Create floor group
  const floorGroup = document.createElementNS("http://www.w3.org/2000/svg", "g");
  floorGroup.setAttribute("id", floorId);
  svgCanvas.appendChild(floorGroup);

  // Restore elements for the target floor
  if (targetFloorData.svgContent) {
    floorGroup.innerHTML = targetFloorData.svgContent;
    // Reattach event listeners to restored elements
    const elements = floorGroup.querySelectorAll(".draggable");
    elements.forEach(elem => {
      const group = elem.parentNode;
      const selectionBox = group.querySelector(".selection-box");
      const resizeHandles = group.querySelectorAll(".resize-handle");
      elem.addEventListener("mousedown", (e) => {
        e.preventDefault();
        e.stopPropagation();
        selectElement({ main: elem, group, selectionBox, resizeHandles });
        startDrag(e, elem);
      });
      if (elem.getAttribute("data-type") === "text") {
        elem.addEventListener("dblclick", (e) => {
          editTextElement(elem, e);
        });
      }
      resizeHandles.forEach(handle => {
        handle.addEventListener("mousedown", (e) => {
          e.preventDefault();
          e.stopPropagation();
          isResizing = true;
          selectedElement = elem;
          startX = getSVGPoint(e).x;
          startY = getSVGPoint(e).y;
          startWidth = parseFloat(elem.getAttribute("width"));
          startHeight = parseFloat(elem.getAttribute("height"));
          const transform = group.getAttribute("transform") || "translate(0, 0)";
          const translateMatch = transform.match(/translate\((-?\d+\.?\d*),\s*(-?\d+\.?\d*)\)/);
          startTranslateX = translateMatch ? parseFloat(translateMatch[1]) : 0;
          startTranslateY = translateMatch ? parseFloat(translateMatch[2]) : 0;
          resizeHandleType = handle.getAttribute("data-handle");
        });
      });
    });
  }

  updateRulersAndGrid();
  updateDetails();
}

function addFloor() {
  // Save the current floor's SVG content
  const currentFloorData = floors.find(f => f.id === currentFloor);
  const currentFloorGroup = svgCanvas.querySelector(`#${currentFloor}`);
  if (currentFloorGroup) {
    currentFloorData.svgContent = currentFloorGroup.outerHTML;
    currentFloorData.undoStack = currentFloorData.undoStack.filter(item => item.floor === currentFloor);
    currentFloorData.redoStack = currentFloorData.redoStack || [];
  }

  // Create and switch to a new floor
  const newFloorId = `floor${floors.length + 1}`;
  floors.push({ id: newFloorId, undoStack: [], redoStack: [], svgContent: "" });
  switchFloor(newFloorId);
  updateFloorsDropdown();
}

function updateFloorsDropdown() {
  const dropdown = document.querySelector('.dropdown-menu');
  dropdown.innerHTML = '';
  floors.forEach(floor => {
    const li = document.createElement('li');
    li.innerHTML = `<a class="dropdown-item" href="#" onclick="switchFloor('${floor.id}')">Floor ${floor.id.replace('floor', '')}</a>`;
    dropdown.appendChild(li);
  });
  const addLi = document.createElement('li');
  addLi.innerHTML = `<a class="dropdown-item" href="#" onclick="addFloor()">Add Floor</a>`;
  dropdown.appendChild(addLi);
}

function showDetails() {
  const currentFloorData = floors.find(f => f.id === currentFloor);
  document.getElementById('elementCount').textContent = currentFloorData.undoStack.length;
  document.getElementById('rulerScale').textContent = document.getElementById('ruler').value;
  new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function updateDetails() {
  const currentFloorData = floors.find(f => f.id === currentFloor);
  document.getElementById('elementCount').textContent = currentFloorData.undoStack.length;
}

function showSettings() {
  new bootstrap.Modal(document.getElementById('settingsModal')).show();
}

function toggleGrid() {
  const grid = document.getElementById('grid');
  grid.style.display = grid.style.display === 'none' ? 'block' : 'none';
  const floorData = floors.find(f => f.id === currentFloor);
  if (floorData) floorData.grid = grid.style.display !== 'none';
}

function toggleRulers() {
  const rulers = document.querySelectorAll('.ruler-top, .ruler-left');
  rulers.forEach(ruler => {
    ruler.style.display = ruler.style.display === 'none' ? 'block' : 'none';
  });
  const floorData = floors.find(f => f.id === currentFloor);
  if (floorData) floorData.rulers = rulers[0].style.display !== 'none';
}

function togglePanel(type) {
  const panel = document.getElementById(`panel-${type}`);
  const button = document.querySelector(`.sidebar button[onclick*="${type}"]`);

  if (activePanel && activePanel !== panel) {
    activePanel.classList.remove('show');
    setTimeout(() => {
      activePanel.style.display = 'none';
    }, 300);
  }

  if (panel.classList.contains('show')) {
    panel.classList.remove('show');
    setTimeout(() => {
      panel.style.display = 'none';
    }, 300);
    activePanel = null;
  } else {
    const rect = button.getBoundingClientRect();
    panel.style.top = `${rect.top}px`;
    panel.style.left = `${rect.right + 5}px`;
    panel.style.display = 'block';
    setTimeout(() => {
      panel.classList.add('show');
    }, 10);
    activePanel = panel;
    initializeDragElements();
  }
}

function undo() {
  const currentFloorData = floors.find(f => f.id === currentFloor);
  const last = currentFloorData.undoStack.pop();
  if (last) {
    currentFloorData.redoStack.push(last);
    last.element.group.remove();
    updateDetails();
  }
}

function redo() {
  const currentFloorData = floors.find(f => f.id === currentFloor);
  const last = currentFloorData.redoStack.pop();
  if (last) {
    svgCanvas.querySelector(`#${currentFloor}`).appendChild(last.element.group);
    currentFloorData.undoStack.push(last);
    updateDetails();
  }
}

async function downloadSVG() {
  const svg = document.getElementById('canvas');
  const clonedSvg = svg.cloneNode(true);

  // Remove grid, selection boxes, and resize handles to clean up the output
  const grid = clonedSvg.querySelector('#grid');
  if (grid) grid.remove();
  clonedSvg.querySelectorAll('.selection-box, .resize-handle').forEach(el => el.remove());

  // Convert all images to data URLs
  const images = clonedSvg.getElementsByTagName('image');
  for (let img of Array.from(images)) {
    const href = img.getAttribute('href');
    if (href && !href.startsWith('data:')) {
      try {
        // Handle external URLs with CORS proxy if needed
        let fetchUrl = href;
        if (href.startsWith('https://img.icons8.com')) {
          fetchUrl = `https://cors-anywhere.herokuapp.com/${href}`; // Use a CORS proxy for external URLs
        }
        const response = await fetch(fetchUrl, { mode: 'cors' });
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        const blob = await response.blob();
        const dataUrl = await new Promise((resolve) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result);
          reader.readAsDataURL(blob);
        });
        img.setAttribute('href', dataUrl);
      } catch (error) {
        console.error(`Failed to load image: ${href}`, error);
        // Fallback: Replace with a placeholder or skip
        img.setAttribute('href', 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50"><rect width="50" height="50" fill="gray"/></svg>');
      }
    }
  }

  // Serialize the SVG
  const serializer = new XMLSerializer();
  let source = serializer.serializeToString(clonedSvg);

  // Ensure proper XML declaration and namespace
  source = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>\n' +
           '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">\n' +
           source;

  // Create and trigger download
  const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `floorplan_${currentFloor}.svg`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
function updateRulersAndGrid() {
  const rulerValue = parseInt(document.getElementById("ruler").value);
  const pixelsPerUnit = rulerValue;
  const majorTickInterval = 100;
  const minorTickInterval = 20;

  // Update top ruler
  const topRuler = document.querySelector(".ruler-top");
  topRuler.innerHTML = "";
  for (let x = 0; x <= 800; x += minorTickInterval) {
    const isMajor = x % majorTickInterval === 0;
    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
    line.setAttribute("x1", x);
    line.setAttribute("y1", isMajor ? 0 : 15);
    line.setAttribute("x2", x);
    line.setAttribute("y2", 30);
    line.setAttribute("stroke", "black");
    topRuler.appendChild(line);
    if (isMajor) {
      const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
      text.setAttribute("x", x);
      text.setAttribute("y", 12);
      text.setAttribute("font-size", "10");
      text.setAttribute("text-anchor", "middle");
      text.textContent = (x / pixelsPerUnit).toFixed(1);
      topRuler.appendChild(text);
    }
  }

  // Update left ruler
  const leftRuler = document.querySelector(".ruler-left");
  leftRuler.innerHTML = "";
  for (let y = 0; y <= 600; y += minorTickInterval) {
    const isMajor = y % majorTickInterval === 0;
    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
    line.setAttribute("x1", isMajor ? 0 : 15);
    line.setAttribute("y1", y);
    line.setAttribute("x2", 30);
    line.setAttribute("y2", y);
    line.setAttribute("stroke", "black");
    leftRuler.appendChild(line);
    if (isMajor) {
      const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
      text.setAttribute("x", 12);
      text.setAttribute("y", y + 4);
      text.setAttribute("font-size", "10");
      text.setAttribute("text-anchor", "middle");
      text.setAttribute("transform", `rotate(-90, 12, ${y + 4})`);
      text.textContent = (y / pixelsPerUnit).toFixed(1);
      leftRuler.appendChild(text);
    }
  }

  // Update grid (with toggle)
  const grid = document.getElementById("grid");
  grid.innerHTML = "";
  if (document.getElementById("gridToggle").checked) {
    for (let x = 0; x <= 800; x += minorTickInterval) {
      const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
      line.setAttribute("x1", x);
      line.setAttribute("y1", 0);
      line.setAttribute("x2", x);
      line.setAttribute("y2", 600);
      line.setAttribute("stroke", "#ccc");
      line.setAttribute("stroke-width", x % majorTickInterval === 0 ? "1" : "0.5");
      grid.appendChild(line);
    }
    for (let y = 0; y <= 600; y += minorTickInterval) {
      const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
      line.setAttribute("x1", 0);
      line.setAttribute("y1", y);
      line.setAttribute("x2", 800);
      line.setAttribute("y2", y);
      line.setAttribute("stroke", "#ccc");
      line.setAttribute("stroke-width", y % majorTickInterval === 0 ? "1" : "0.5");
      grid.appendChild(line);
    }
  }

  // Store floor settings
  const currentFloorData = floors.find(f => f.id === currentFloor);
  if (currentFloorData) {
    currentFloorData.rulerScale = rulerValue;
    currentFloorData.grid = document.getElementById("gridToggle").checked;
    currentFloorData.rulers = document.getElementById("rulerToggle").checked;
  }
}

// Search functionality
document.querySelector("#panel-search input").addEventListener("input", (e) => {
  const query = e.target.value.toLowerCase().trim();
  const results = document.querySelector(".search-results");
  results.innerHTML = "";
  if (query) {
    const filteredItems = items.filter(
      (item) =>
        item.label.toLowerCase().includes(query) ||
        item.type.toLowerCase().includes(query) ||
        item.category.toLowerCase().includes(query)
    );
    if (filteredItems.length > 0) {
      filteredItems.forEach((item) => {
        const container = document.createElement("div");
        container.className = "element-container";
        const img = document.createElement("img");
        img.src = item.src;
        img.draggable = true;
        img.className = "element";
        img.dataset.type = item.type;
        const label = document.createElement("span");
        label.className = "element-label";
        label.textContent = item.label;
        container.appendChild(img);
        container.appendChild(label);
        results.appendChild(container);
      });
      initializeDragElements();
    } else {
      results.innerHTML = '<div class="not-found">No items found</div>';
    }
  }
});

// Toggle panel item options
document.querySelectorAll(".panel-item-toggle").forEach((toggle) => {
  toggle.addEventListener("click", () => {
    const options = toggle.nextElementSibling;
    const isOpen = options.style.display === "flex";
    document
      .querySelectorAll(".panel-item-options")
      .forEach((opt) => (opt.style.display = "none"));
    options.style.display = isOpen ? "none" : "flex";
    toggle.textContent = toggle.textContent.replace(
      isOpen ? "▼" : "▲",
      isOpen ? "▲" : "▼"
    );
  });
});

// Save design to database
async function saveDesign() {
  // Update current floor's SVG content
  const currentFloorData = floors.find(f => f.id === currentFloor);
  const currentFloorGroup = svgCanvas.querySelector(`#${currentFloor}`);
  if (currentFloorGroup) {
    currentFloorData.svgContent = currentFloorGroup.outerHTML;
  }

  // Prepare data
  const json_layout = JSON.stringify(floors);
  const svg_data = svgCanvas.outerHTML;

  // Send to server
  try {
    const response = await fetch('save_design.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        proposal_id: new URLSearchParams(window.location.search).get('proposal_id') || '0',
        design_id: new URLSearchParams(window.location.search).get('load_id') || '0',
        json_layout,
        svg_data
      })
    });
    const result = await response.json();
    if (result.success) {
      alert('Design saved successfully!');
    } else {
      alert('Failed to save design: ' + result.message);
    }
  } catch (error) {
    console.error('Save error:', error);
    alert('Error saving design.');
  }
}

// Initialize on load
document.addEventListener("DOMContentLoaded", () => {
  updateRulersAndGrid();
  updateFloorsDropdown();
  initializeDragElements();

  // Reinitialize draggable elements for saved floors
  floors.forEach((floor) => {
    if (floor.svgContent) {
      const tempSvg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      tempSvg.innerHTML = floor.svgContent;
      const elements = tempSvg.querySelectorAll(".draggable");
      elements.forEach((elem) => {
        elem.addEventListener("mousedown", (e) => {
          e.preventDefault();
          e.stopPropagation();
          const group = elem.parentNode;
          const selectionBox = group.querySelector(".selection-box");
          const resizeHandles = group.querySelectorAll(".resize-handle");
          selectElement({ main: elem, group, selectionBox, resizeHandles });
          startDrag(e, elem);
        });
        if (elem.getAttribute("data-type") === "text") {
          elem.addEventListener("dblclick", (e) => {
            editTextElement(elem, e);
          });
        }
      });
    }
  });

  // Initialize panel options
  document.querySelectorAll(".panel-item-options").forEach((opt) => {
    opt.style.display = "none";
  });
});

updateFloorsDropdown();
updateRulersAndGrid();
  </script>
</body>

</html>