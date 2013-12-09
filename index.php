<?php

include_once("ColorCataloging.class.php");

if(empty($_GET)) //--all
{
    $colorCataloging = new ColorCataloging();
    var_dump($colorCataloging->ProcessAll());
}
else if($_GET["media_id"] > 0)
{
    $colorCataloging = new ColorCataloging();
    var_dump($colorCataloging->ProcessOne($_GET["media_id"]));
}
else
{
    echo "Invalid request";
}
