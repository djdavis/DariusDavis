<?php
require("inc/config.inc.php");
error_reporting(0);
if (!isset($_GET['file']) || ( !isset($_GET['depot']) && (!isset($_GET['toDepot']) || !isset($_GET['fromDepot'])) ) ) {
	die('Invalid parameters');
} elseif (!is_dir($dir)) {
	die('Invalid directory');
} elseif ($_GET['job'] == "print_transfer" && !isset($_GET['transferId'])) {
	die('Invalid parameters');
}
// Proceed...
$depot		= stripslashes($_GET['depot']);
$filename	= stripslashes($_GET['file']);
$toDepot	= stripslashes($_GET['toDepot']);
$fromDepot	= stripslashes($_GET['fromDepot']);
$quote		= stripslashes($_GET['quote']);
$subDir		= $_GET['subDir'];
$w_filename	= str_replace(".pdf","_".$depot,$filename).".pdf";
$allowedRecipients = array(13,17,20,23,30,35,40,41,70,71,73,15,31,50,10); // (Kansas, Minnesota, Houston, Washington not using as of 7/31/13 [added on 4/1/14 at Tom's request]) [Removed 43 on 4/28]

if (isset($_GET['tri']) && $_GET['tri'] == "119") {
	$tri = "119";
} elseif ($_GET['tri'] == "117") {
	$tri = "117";
}

// $prac_dir: practice directory (0.119) | $dir: live directory (0.117)
// Checks the live & dev server directories (for testing purposes).
// All documents printed from *.119 are routed to the iR3225 upstairs.
if ((is_file($dir. $subDir . DIRECTORY_SEPARATOR .$filename) || is_file($prac_dir. $subDir . DIRECTORY_SEPARATOR .$filename)) && getPrinter($depot) !== false && $_GET['job'] == "print") {
	$toDepot = ''; $fromDepot = '';
	if ((!strstr($_SERVER['HTTP_REFERER'],".119") || !strstr($_SERVER['HTTP_REFERER'],"intranet-dev")) && $tri != "119") {
		$tri = "117";
		// Print to Shipping Location
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .$w_filename.'"  -print-to "'.getPrinter($depot).'" -silent');
		// Print to Chicago [Pricing]
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .$filename.'"  -print-to "'.$prntSrvr. DIRECTORY_SEPARATOR .'Canon iR-ADV 6255/6265 PCL5e" -silent'); 
		/* exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .$filename.'"  -print-to "'.$prntSrvr. DIRECTORY_SEPARATOR .'Canon Color iR-ADV C5030/5035 UFR II" -silent'); */
		/* exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .$filename.'"  -print-to "'.$prntSrvr. DIRECTORY_SEPARATOR .'Canon iR-ADV C5235" -silent'); */

		if ($subDir == "modBeams") {
			// If its an order for mod beams, print a copy to the warehouse:
			exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .$filename.'"  -print-to "'.getPrinter($depot).'" -silent');
		}
	} else { 
		// If their source is .119 (PRAC. SERVER), print upstairs.
		$tri = "119";
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$prac_dir. $subDir . DIRECTORY_SEPARATOR .$filename.'"  -print-to "Canon iR5035 color" -silent');
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$prac_dir. $subDir . DIRECTORY_SEPARATOR .$w_filename.'"  -print-to "Canon iR5035 color" -silent');
	}

 	if (!strstr($_SERVER['HTTP_REFERER'],".119") && !strstr($_SERVER['HTTP_REFERER'],"intranet-dev") && $tri != "119") {
		if (in_array($depot,$allowedRecipients)) {
			// Testing out test message -- Get Foreman Cell			
			
			if (count(getForemanByLoc($depot)) == 1) {
				$forman = getForemanByLoc($depot);
				$query = mysql_query("SELECT cell1 FROM `pr_resp_detail` WHERE respid = '".$forman[0]."'");
				if (mysql_num_rows($query) > 0) {
					$data = mysql_fetch_assoc($query);
					$headers  = 'MIME-Version: 1.0' . "\r\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
					//$headers .= 'Cc: darius.davis@lgh-usa.com,thomas.beasley@lgh-usa.com,dan.konsoer@lgh-usa.com' . "\r\n";
					$headers .= 'From: LGH Mailer <admin@lgh-usa.com>' . "\r\n";
					mail(str_replace("-","",$data['cell1'])."@vtext.com","New Order!","Please check your printer for a new order.",$headers);
					$additionalNote = " Text message sent to ".str_replace("-","",$data['cell1'])."@vtext.com";
				}
			} else {		
				foreach (getForemanByLoc($depot) as $forMan) {
					$query = mysql_query("SELECT cell1 FROM `pr_resp_detail` WHERE respid = '".$forMan."'");
					if (mysql_num_rows($query) > 0) {
						$data = mysql_fetch_assoc($query);
						$headers  = 'MIME-Version: 1.0' . "\r\n";
						$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
						//$headers .= 'Cc: darius.davis@lgh-usa.com' . "\r\n";
						$headers .= 'From: LGH Mailer <admin@lgh-usa.com>' . "\r\n";
						mail(str_replace("-","",$data['cell1'])."@vtext.com","New Order!","Please check your printer for a new order.",$headers);
						
					}
					$additionalNote .= " <br />Text message sent to ".str_replace("-","",$data['cell1'])."@vtext.com <Br/>";
				}
			}
		}
	}
		
	$confirmation 	= "<h2 class='sectitle'>Your order has been successfully printed!</h2>";
	$confirmation  .= "<p><a href='http://192.168.0.".$tri."/outbound/".$subDir."/".$filename."'>Your document</a> was printed to <strong>".getLocation($depot)."</strong> and your local printer. ".$additionalNote."</p>";
	$confirmation .=" <p><a href='http://192.168.0.".$tri."/outbound/".$subDir."/".$w_filename."' target='_blank'>Click here for warehouse copy</a> - open and print to the warehouse.</p>";
	
} elseif ((is_file($dir. $subDir . DIRECTORY_SEPARATOR .$filename) || is_file($prac_dir. $subDir . DIRECTORY_SEPARATOR .$filename)) &&  (getPrinter($toDepot) !== false && getPrinter($fromDepot) !== false) && $_GET['job'] == "print_transfer") {
	$transferID = $_GET['transferId'];
	$depot = '';
	if (!strstr($_SERVER['HTTP_REFERER'],".119") || !strstr($_SERVER['HTTP_REFERER'],"intranet-dev")) {
		$tri = "117";
		// Print to Delivery Location
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .'transferDeliv'.$transferID.'.pdf"  -print-to "'.getPrinter($toDepot).'" -silent');	
		// Print to Shipping Location
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .'transferShip'.$transferID.'.pdf"  -print-to "'.getPrinter($fromDepot).'" -silent');		
		// Print to Chicago Location [i have no idea wtf this is for]
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .'transferShip'.$transferID.'-CUST.pdf"  -print-to "Canon iR5050 PCL6" -silent');
		/*exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$dir. $subDir . DIRECTORY_SEPARATOR .'transferShip'.$transferID.'-CUST.pdf"  -print-to "'.$prntSrvr. DIRECTORY_SEPARATOR .'Canon Color iR-ADV C5030/5035 UFR II" -silent');*/
	} else { 
		// If their source is .119 (PRAC. SERVER), print upstairs. - use $prac_dir
		$tri = "119";
		// Print to Delivery Location
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$prac_dir. $subDir . DIRECTORY_SEPARATOR .'transferDeliv'.$transferID.'.pdf"  -print-to "Canon iR5035 color"  -silent');	
		// Print to Shipping Location
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$prac_dir. $subDir . DIRECTORY_SEPARATOR .'transferShip'.$transferID.'.pdf"  -print-to "Canon iR5035 color"  -silent');
			
		// Print to Chicago Location
		exec('"C:\Program Files\SumatraPDF\SumatraPDF.exe" "'.$prac_dir. $subDir . DIRECTORY_SEPARATOR .'transferShip'.$transferID.'-CUST.pdf"  -print-to "Canon iR5035 color" -silent');
	}
	
 	if (!strstr($_SERVER['HTTP_REFERER'],".119") || !strstr($_SERVER['HTTP_REFERER'],"intranet-dev")) {
		if (in_array($depot,$allowedRecipients)) {
			// Testing out test message -- Get Foreman Cell
			if (count(getForemanByLoc($depot)) == 1) {
				$query = mysql_query("SELECT cell1 FROM `pr_resp_detail` WHERE respid = '".getForemanByLoc($depot)."'");
				if (mysql_num_rows($query) > 0) {
					$data = mysql_fetch_assoc($query);
					$headers  = 'MIME-Version: 1.0' . "\r\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
					//$headers .= 'Cc: darius.davis@lgh-usa.com,thomas.beasley@lgh-usa.com,dan.konsoer@lgh-usa.com' . "\r\n";
					$headers .= 'From: LGH Mailer <admin@lgh-usa.com>' . "\r\n";
					mail(str_replace("-","",$data['cell1'])."@vtext.com","New Transfer Request!","Please check your printer for a new transfer request.",$headers);
				}
			} else {
				foreach (getForemanByLoc($depot) as $forMan) {
					$query = mysql_query("SELECT cell1 FROM `pr_resp_detail` WHERE respid = '".$forMan."'");
					if (mysql_num_rows($query) > 0) {
						$data = mysql_fetch_assoc($query);
						$headers  = 'MIME-Version: 1.0' . "\r\n";
						$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
						//$headers .= 'Cc: darius.davis@lgh-usa.com,thomas.beasley@lgh-usa.com,dan.konsoer@lgh-usa.com' . "\r\n";
						$headers .= 'From: LGH Mailer <admin@lgh-usa.com>' . "\r\n";
						mail(str_replace("-","",$data['cell1'])."@vtext.com","New Transfer Request!","Please check your printer for a new transfer request.",$headers);
					}
				}
			}
		}
	}
	
	$confirmation 	= "<h2 class='sectitle'>Your transfer request has been successfully printed!</h2>";
	$confirmation  .= "<p><a href='http://192.168.0.".$tri."/outbound/".$subDir."/".$filename."'>Your documents</a> were printed to <strong>".getLocation($toDepot)."</strong>,  <strong>".getLocation($fromDepot)."</strong> and your local printer.</p>";
	
} elseif ($_GET['job'] == "fax") {
	$confirmation 	= "<h2 class='sectitle'>Your order has been successfully faxed!</h2>";
	$confirmation  .= "<p><a href='http://192.168.0.".$tri."/outbound/".$subDir."/".$filename."'>Your document</a> was faxed to <strong>".getLocation($depot)." (".getFaxNo($depot).")</strong>.</p>";
} elseif ($_GET['job'] == "email") {
	$confirmation 	= "<h2 class='sectitle'>Your order has been successfully e-mailed!</h2>";
	$confirmation  .= "<p><a href='http://192.168.0.".$tri."/outbound/".$subDir."/".$filename."'>Your document</a> was e-mailed to <strong>".getLocation($depot)." (".getUserEmail(getForemanByLoc($depot)).")</strong>.</p>";
} else {
	$confirmation 	= "<h2 class='sectitle'>An error has occurred!</h2>";
	$confirmation  .= "<p>Your document was not printed, faxed or e-mailed. Please try again.</p>";
}

// Display Response
require_once("inc/confirm.inc.php");
?>
