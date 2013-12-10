<?php

include_once("ColorCataloging.class.php");
include_once("ColorCatalogingResponse.class.php");

/*
 * Writtes to the browser a quick (and uggly :P) preview of the result array.
*/
function QuickResultPreview($result)
{
    if($result->code == -1)
    {
        echo $result;
        return;
    }   
    
    foreach($result->result as $image)
    {      
        echo '<img src="'.$image->imageUrl.'"/>';
        echo "<table border=1>";
        
        foreach($image->colors as $color=>$percent)
        {
            echo "<tr>";
            echo '<td bgcolor="#'.$color.'" width="100">&nbsp;&nbsp;&nbsp;</td>';
            echo '<td width="100">'.$color.'</td>';
            echo '<td width="100">'.$percent.'</td>';
            echo "</tr>";
        }
        echo "</table>";
    }
    
    
}

$previewResult = 0;
if(isset($_GET["preview"]))
{
    $previewResult = $_GET["preview"];
}

if(isset($_GET["media_id"]))
{
    $media_id = $_GET["media_id"];
}

$result = Array();

if(!isset($media_id)) //--all
{
    $colorCataloging = new ColorCataloging();
    $result= $colorCataloging->ProcessAll();
}
else if($media_id  > 0)
{
    $colorCataloging = new ColorCataloging();
    $result = $colorCataloging->ProcessOne($media_id);
}
else
{
    echo "Invalid request";
    die;
}

if($previewResult == 1)
{
    QuickResultPreview($result);
}else
{
    echo json_encode($result);
}
