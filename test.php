<?php
include("config.php"); //Includes connection to the database
include("functions.php"); //Includes connection to the database
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.bundle.min.js"></script>

<h1> Checkbox inputs as cards </h1>

<div class="grid-wrapper">

  <div class="card-wrapper">
    <input class="c-card" type="checkbox" id="1" value="1" checked="checked">
    <div class="card-content">
      <div class="card-state-icon"></div>
      <label for="1">
        <div class="image"></div>
        <h4>Subject</h4>
        <h5>Type &bull; something else</h5>
        <p class="small-meta dim">Date sent</p>
      </label>
    </div>
  </div>
  
  <div class="card-wrapper">
    <input class="c-card" type="checkbox" id="2" value="2" checked="checked">
    <div class="card-content">
      <div class="card-state-icon"></div>
      <label for="2">
        <div class="image"></div>
        <h4>Subject</h4>
        <h5>Type &bull; something else</h5>
        <p class="small-meta dim">Date sent</p>
      </label>
    </div>
  </div>
  
  <div class="card-wrapper">
    <input class="c-card" type="checkbox" id="3" value="3">
    <div class="card-content">
      <div class="card-state-icon"></div>
      <label for="3">
        <div class="image"></div>
        <h4>Subject</h4>
        <h5>Type &bull; something else</h5>
        <p class="small-meta dim">Date sent</p>
      </label>
    </div>
  </div>
  
  <div class="card-wrapper">
    <input class="c-card" type="checkbox" id="4" value="4">
    <div class="card-content">
      <div class="card-state-icon"></div>
      <label for="4">
        <div class="image"></div>
        <h4>Subject</h4>
        <h5>Type &bull; something else</h5>
        <p class="small-meta dim">Date sent</p>
      </label>
    </div>
  </div>
  
  
  
</div>