<?php

include_once("ColorCataloging.class.php");

function QuickResultPreview($result)
{
    
    foreach($result as $image)
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


if(empty($_GET)) //--all
{
    $colorCataloging = new ColorCataloging();
    //var_dump($colorCataloging->ProcessAll());
    QuickResultPreview($colorCataloging->ProcessAll());
}
else if(($media_id = $_GET["media_id"]) > 0)
{
    $colorCataloging = new ColorCataloging();
    //var_dump($colorCataloging->ProcessOne($media_id));
    QuickResultPreview($colorCataloging->ProcessOne($media_id));
}
else
{
    echo "Invalid request";
}
