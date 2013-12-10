<?php

include_once ('ColorCatalogingResult.class.php');

/*
 * Class used to process images and obtain the most significant colors containeds on it. 
 */
class ColorCataloging
{
        //Urls from where the images to process are going to be obtained.
        private $ALL_IMG_API_URL = "http://api.photorank.me/v1/photos?api_key=f8f2fd79733705690041326b268d5b09eb6f264b9101b08a26f96fe2a89e5adc";
        private $ID_IMG_API_URL = "http://api.photorank.me/v1/photos/%u?api_key=f8f2fd79733705690041326b268d5b09eb6f264b9101b08a26f96fe2a89e5adc";
      
        public $delta = 32;
        
        //Reduces colors that are close to each other.
        public $reduceGradient = true;
        
        //Reduces colors that are the same but varies on brightness. 
        public $reduceBrightness = false;
        
        //max colors returned by the ProcessAll and ProcessOne methods.
        public $maxColorsDettected = 15;

        /*
         * Returns a decoded JSON with a list of images obtained from the URL passed.
         */
        private function GetImagesListFromURL($url)
        {            
            $json = file_get_contents($url, 0, null, null);
            $decodedJson = json_decode($json);
            return $decodedJson;
        }
        
        /*
         * Process all images obtained from calling the photorank API. 
         */
        public function ProcessAll()
        {
            try
            {
                $imageList = $this->GetImagesListFromURL($this->ALL_IMG_API_URL);

                $result = array();

                foreach ($imageList->response as $image) {
                    $colorResult = new ColorCatalogingResult();
                    $colorResult->id = $image->id;
                    $colorResult->imageUrl = $image->images->thumbnail;
                    $colorResult->colors = $this->GetImageColors($image->images->thumbnail);
                    $result[] = $colorResult;

                }
                
                return $this->ReturnValidResponse($result);
            }
            catch(Exception $e)
            {
                return $this->ReturnErrorResponse("An error ocurred processing the images: \n".$e);
            }
            
        }
        
        /*
         * Process the image with the id passed as parameter.
         */
        public function ProcessOne($imageId)
        {
            try
            {
                $image = $this->GetImagesListFromURL(sprintf($this->ID_IMG_API_URL,$imageId));
                $colorResult = new ColorCatalogingResult();
                $colorResult->id = $image->response->id;
                $colorResult->imageUrl = $image->response->images->thumbnail;
                $colorResult->colors = $this->GetImageColors($image->response->images->thumbnail);
                $result[] = $colorResult;
                return $this->ReturnValidResponse($result);
            }
            catch(Exception $e)
            {
                return $this->ReturnErrorResponse("An error ocurred processing the image: \n".$e);
            }
        }
        
        /*
         * Returns a response containing just an error message.
         */
        private function ReturnResponse($errorMessage)
        {
            $response = new ColorCatalogingResponse();
            $response->code = -1;
            $response->result = $errorMessage;
            return $response;            
        }
        
        private function ReturnValidResponse($result)
        {
            $response = new ColorCatalogingResponse();
            $response->code = 0;
            $response->result = $result;
            return $response;            
        }
        
        /*
         * Process an image a returns an array with the most significant colors.
         */        
        private function GetImageColors($imageFile)
        {           
            $image = new Imagick();
            $image->readimage($imageFile);
            
            
            if($this->delta > 2)
            {
                $halfDelta = $this->delta / 2 - 1;
            }
            else
            {
                $halfDelta = 0;
            }
            
            $imgWidth = $image->getimagewidth();
            $imgHeight = $image->getimageheight();
            
            $totalPixelCount = 0;
            
            for ($y=0; $y < $imgHeight; $y++)
            {
                for ($x=0; $x < $imgWidth; $x++)
                {
                    $totalPixelCount++;
                    $pixelColor = $image->getimagepixelcolor($x,$y);
                    $colors = $pixelColor->getColor();
                    
                    // round the colors, to reduce the number of duplicate colors.
                    if ( $this->delta > 1 )
                    {
                        
                        $colors['r'] = min(255,intval((($colors['r'])+$halfDelta)/$this->delta)*$this->delta);
                        $colors['g'] = min(255,intval((($colors['g'])+$halfDelta)/$this->delta)*$this->delta);
                        $colors['b'] = min(255,intval((($colors['b'])+$halfDelta)/$this->delta)*$this->delta);
                        
                    }

                    $hex = $this->RGBToHexString($colors['r'], $colors['g'], $colors['b']);

                    if (!isset($hexarray[$hex]))
                    {
                        $hexarray[$hex] = 1;
                    }
                    else
                    {
                        $hexarray[$hex]++;
                    }
                }
            }
            
            if($this->reduceGradient == true)
            {
                $this->ReduceGradientsOnArray($hexarray);
            }
            
            if($this->reduceGradient == true)
            {
                $this->ReduceGradientsOnArray($hexarray);
            }
            
            arsort( $hexarray, SORT_NUMERIC );

            // convert counts to percentages
            foreach ($hexarray as $key => $value)
            {
                    $hexarray[$key] = (float)$value / $totalPixelCount *100;
            }
            
            if($this->maxColorsDettected > 0)
            {
                return array_slice($hexarray, 0, $this->maxColorsDettected, true);
            }
            else 
            {
                return $hexarray;
            }
             
        }
        
        /*
         * Reduces colors on an array that are close to each other.
         */
        private function ReduceGradientsOnArray(&$colorsArray)
        {
            
            arsort( $colorsArray, SORT_NUMERIC );

            $gradients = array();
            
            foreach ($colorsArray as $hex => $num)
            {
                if ( ! isset($gradients[$hex]) )
                {
                    $new_hex = $this->FindAdjacentColor( $hex, $gradients, $this->delta );
                    $gradients[$hex] = $new_hex;
                }
                else
                {
                    $new_hex = $gradients[$hex];
                }

                if ($hex != $new_hex)
                {
                    $colorsArray[$hex] = 0;
                    $colorsArray[$new_hex] += $num;
                }
            }
            
        }
        
        /*
         * Reduces on the array the variatons of the same color that are brighter or darker.
         */
        private function ReduceBrightnessOnArray(&$colorsArray)
        {            
            arsort( $colorsArray, SORT_NUMERIC );

            $brightness = array();
            foreach ($colorsArray as $hex => $num)
            {
                if ( !isset($brightness[$hex]) )
                {
                    $new_hex = $this->Normalize( $hex, $brightness, $this->delta);
                    $brightness[$hex] = $new_hex;
                }
                else
                {
                    $new_hex = $brightness[$hex];
                }

                if ($hex != $new_hex)
                {
                    $colorsArray[$hex] = 0;
                    $colorsArray[$new_hex] += $num;
                }
            }            
        }

        /*
         * Reduces colors bassed on their light variation.
         */
	private function Normalize( $hex, $hexarray, $delta )
	{
            $lowest = 255;
            $highest = 0;
            $colors['red'] = hexdec( substr( $hex, 0, 2 ) );
            $colors['green']  = hexdec( substr( $hex, 2, 2 ) );
            $colors['blue'] = hexdec( substr( $hex, 4, 2 ) );

            if ($colors['red'] < $lowest)
            {
                    $lowest = $colors['red'];
            }
            if ($colors['green'] < $lowest )
            {
                    $lowest = $colors['green'];
            }
            if ($colors['blue'] < $lowest )
            {
                    $lowest = $colors['blue'];
            }

            if ($colors['red'] > $highest)
            {
                    $highest = $colors['red'];
            }
            if ($colors['green'] > $highest )
            {
                    $highest = $colors['green'];
            }
            if ($colors['blue'] > $highest )
            {
                    $highest = $colors['blue'];
            }

            // Do not normalize white, black, or shades of grey unless low delta
            if ( $lowest == $highest )
            {
                if ($delta <= 32)
                {
                    if ( $lowest == 0 || $highest >= (255 - $delta) )
                    {
                            return $hex;
                    }
                }
                else
                {
                    return $hex;
                }
            }

            for (; $highest < 256; $lowest += $delta, $highest += $delta)
            {
                $new_hex = $this->RGBToHexString($colors['red'] - $lowest, $colors['green'] - $lowest, $colors['blue'] - $lowest);
                
                if ( isset( $hexarray[$new_hex] ) )
                {
                    // same color, different brightness - use it instead
                    return $new_hex;
                }
            }

            return $hex;
	}

        /*
         * Finds colors that are close to each other.
         */
	private function FindAdjacentColor( $hex, $gradients, $delta )
	{
            $red = hexdec( substr( $hex, 0, 2 ) );
            $green  = hexdec( substr( $hex, 2, 2 ) );
            $blue = hexdec( substr( $hex, 4, 2 ) );

            if ($red > $delta)
            {
                $new_hex = $this->RGBToHexString($red - $delta, $green, $blue);
                if ( isset($gradients[$new_hex]) )
                {
                        return $gradients[$new_hex];
                }
            }
            if ($green > $delta)
            {
                $new_hex = $this->RGBToHexString($red, $green - $delta, $blue);
                if ( isset($gradients[$new_hex]) )
                {
                        return $gradients[$new_hex];
                }
            }
            if ($blue > $delta)
            {
                $new_hex = $this->RGBToHexString($red, $green, $blue -$delta);
                if ( isset($gradients[$new_hex]) )
                {
                        return $gradients[$new_hex];
                }
            }

            if ($red < (255 - $delta))
            {
                $new_hex = $this->RGBToHexString($red + $delta, $green, $blue);
                if ( isset($gradients[$new_hex]) )
                {
                        return $gradients[$new_hex];
                }
            }
            if ($green < (255 - $delta))
            {
                $new_hex = $this->RGBToHexString($red, $green + $delta, $blue);
                if ( isset($gradients[$new_hex]) )
                {
                        return $gradients[$new_hex];
                }
            }
            if ($blue < (255 - $delta))
            {
                $new_hex = $this->RGBToHexString($red, $green, $blue + $delta);
                if ( isset($gradients[$new_hex]) )
                {
                        return $gradients[$new_hex];
                }
            }

            return $hex;
	}
        
        /*
         * Converts 3 ints (R, G, B) to a Hex color string.
         */
        private function RGBToHexString($red, $green, $blue)
        { 
            return substr("0".dechex($red),-2).substr("0".dechex($green),-2).substr("0".dechex($blue),-2);
        }
}
