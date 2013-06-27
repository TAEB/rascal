<?php
//vt102.php


function parseVT102($input, $ascii_array, $make_report)
{
	static $index=0;
	$posX = 0;
	$posY = 0;
	
	$input = str_replace(chr(0x00), "", $input);//remove NULLS; dont seem to need them.
	$input .= chr(0xFF);//a terminator of sorts
	
	while($input[$counter] !== chr(0xFF))
	{
		if($input[$counter] === chr(0x1B))//this signals the start of a command
		{
			$counter=$counter+2;
			while(!ctype_alpha($input[$counter]))
			{
				$params .= $input[$counter]; //find the parameters for this instruction
				$counter++;
			}
			$instruction = $input[$counter];

			switch($instruction)	//switch the character to find out which command it is
			{						//if the command had parameters apply them
									//generate ascii ouput

				case "H"://home cursor(top left) or if with parameters go to position
					if(strpos($params, ";"))
					{	
						$a = explode(";",$params);
						
						$posY = $a[0];
						$posX = $a[1];
					}
					else//home the cursor
					{
						$posY = 1;
						$posX = 1;
					}	
				break;
				case "A"://nudge cursor up
					if($posY>1)
						$posY--;
				break;
				case "B"://nudge cursor down
					if($posY<25)
					$posY++;
				break;
				case "C"://nudge cursor right
					if($posX<80)
					$posX++;
				break;
				case "D"://nudge cursor down
					if($posX>1)
					$posX--;
				break; 
				case "J"://horizontal erase
					if($params == 1)		//erase all rows from current cursor postion upwards  including cursor position
					{
						for($y = $posY; $y>0; $y--)
						{
							for($x = 0; $x<80; $x++)
							{
								$ascii_array[$x][$y] = " ";//insert a space to every spot
							}
						}
					}
					else if($params == 2)	//erase the entire screen
					{
						for($y = 0; $y<25; $y++)
						{
							for($x = 0; $x<80; $x++)
							{
								$ascii_array[$x][$y] = " ";//insert a space to every spot
							}
						}
					}
					else					//no parameter: erase all rows from current cursor postion downwards  including cursor position
					{
						for($y = $posY; $y<25; $y++)
						{
							for($x = 0; $x<80; $x++)
							{
								$ascii_array[$x][$y] = " ";//insert a space to every spot
							}
						}				
					}
				break;
				case "K"://row erase
					if($params == 1)			//erase the start of the current row to the cursor postion  including cursor position
					{
						for($x = $posX; $x>0; $x--)
						{
							$ascii_array[$x][$posY] = " ";//insert a space to every spot
						}
					}
					else if($params == 2)		//erase the entire row
					{
						for($x = 0; $x<80; $x++)
						{
							$ascii_array[$x][$posY] = " ";//insert a space to every spot
						}
					}
					else						//erase the end of the row from the cursor postion  including cursor position
					{
						for($x = $posX; $x<80; $x++)
						{
							$ascii_array[$x][$posY] = " ";//insert a space to every spot
						}
					}
				break;
				case "m"; //color change
					//not yet implemented
				break;
				default://other commands will not be processed
				break;
			}

		}
		$counter++;
		while($input[$counter] !== chr(0x1B) && $input[$counter] !== chr(0xFF))//this means we have found normal text
		{
			$strbuf.=$input[$counter];
			$counter++;
		}
		//strip out control chars
		$strbuf = preg_replace('/[\x00-\x1F\x7F]/','',$strbuf);

		for($i = 0; $i<strlen($strbuf); $i++)//copy the string into the ascii grid
		{
			$ascii_array[$posX++][$posY] = $strbuf[$i];
        }

		


		
		//the below 4 lines will use alot of memory..they are only needed for using the function make_debugging_reports() otherwise it is safe to	//comment them out
		$parsedstring = $index." P:".$params."\tI:".$instruction."\tX:".$posX."\tY:".$posY."\tS:".$strbuf."\n";
		$bigarray[$index] = $ascii_array;
		$bigarray2[$index] = $parsedstring;
		$index++;
		
		$strbuf= "";//reset the string buffer
		$params= "";//reset parameters


	}
	if($make_report)
	{
		make_debugging_reports($bigarray, $bigarray2, $index);
	}

	$nethackData[0] = $ascii_array;
	$nethackData[1] = array( 0=>$posY, 1=>$posX);
	return $nethackData;

}


function make_debugging_reports($bigarray, $bigarray2, $index)
{
	echo "creating debugging reports";
	//this must be called from parseVT102()
	//this will produce a possibly *VERY* large file depending on how many vt102 instructions went through parseVT102()
	$fp = fopen("c:/rascal/map.txt", "w");
	fclose($fp);
	$fp2 = fopen("c:/rascal/parseddumplog.txt", "w");
	fclose($fp2);


	$fp = fopen("c:/rascal/map.txt", "a");
	$fp2 = fopen("c:/rascal/parseddumplog.txt", "a");
	for($i = 0; $i<$index; $i++ )
	{
		echo ".";//since this might take a while let the user know the program isnt frozen
		fwrite($fp,"\n".$bigarray2[$i]."\n");
		fwrite($fp,"\t.1  .5   .10  .15  .20  .25  .30  .35  .40  .45  .50  .55  .60  .65  .70  .75  .80  .85  .90\n");
		for($y = 0; $y<25; $y++)
		{	
		fwrite($fp,$y."\t");
		for($x = 0; $x<80; $x++)
			{
				fwrite($fp,$bigarray[$i][$x][$y]);
			}
			fwrite($fp, "\n");
		}
		fwrite($fp2, $bigarray2[$i]);
	}
	fclose($fp);
	fclose($fp2);
}
?>