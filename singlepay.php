<?php
header("Content-Type: text/html; charset=ISO-8859-1");
include ("config.php");
include ($oauth_file);

session_start();

$db_filename = "transactionData.db";
$scope = "PAYMENT";
$newTransaction = $_REQUEST["newTransaction"];
$getTransactionStatus = $_REQUEST["getTransactionStatus"];
$refundTransaction = $_REQUEST["refundTransaction"];
$refundReasonText = "User did not like product";
$trxId = $_SESSION["pay1_trxId"];
if($trxId==null || $trxId == "")
    $trxId = "";
$trxIdRefund = $_REQUEST["trxIdRefund"];
if($trxIdRefund==null || $trxIdRefund == "")
    $trxIdRefund = "";
$merchantTrxId = $_REQUEST["merchantTrxId"];
if($merchantTrxId==null || $merchantTrxId == "")
  $merchantTrxId = $_SESSION["pay1_merchantTrxId"];
$authCode = $_REQUEST["TransactionAuthCode"];
if($authCode==null || $authCode == "")
    $authCode = $_SESSION["pay1_authCode"];
$consumerId = $_REQUEST["consumerId"];
if($consumerId==null || $consumerId == "")
    $consumerId = $_SESSION["pay1_consumerId"];
if($consumerId==null || $consumerId == "")
    $consumerId = "";
$product = 0;
if($_REQUEST["product"]!=null)
    $product = $_REQUEST["product"];
$amount = "";
$description = "";
$merchantProductId = "";
if($product==1) {
    $amount = "0.99";
    $description = "Word Game 1";
    $merchantProductId = "WordGame1";
} else if($product==2) {
    $amount = "2.99";
    $description = "Number Game 1";
    $merchantProductId = "NumberGame1";
}
function RefreshToken($FQDN,$api_key,$secret_key,$scope,$fullToken){

  $refreshToken=$fullToken["refreshToken"];
  $accessTok_Url = $FQDN."/oauth/token";

  //http header values
  $accessTok_headers = array(
			     'Content-Type: application/x-www-form-urlencoded'
			     );

  //Invoke the URL
  $post_data="client_id=".$api_key."&client_secret=".$secret_key."&refresh_token=".$refreshToken."&grant_type=refresh_token";

  $accessTok = curl_init();
  curl_setopt($accessTok, CURLOPT_URL, $accessTok_Url);
  curl_setopt($accessTok, CURLOPT_HTTPGET, 1);
  curl_setopt($accessTok, CURLOPT_HEADER, 0);
  curl_setopt($accessTok, CURLINFO_HEADER_OUT, 0);
  //  curl_setopt($accessTok, CURLOPT_HTTPHEADER, $accessTok_headers);
  curl_setopt($accessTok, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($accessTok, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($accessTok, CURLOPT_POST, 1);
  curl_setopt($accessTok, CURLOPT_POSTFIELDS,$post_data);
  $accessTok_response = curl_exec($accessTok);
  $currentTime=time();

  $responseCode=curl_getinfo($accessTok,CURLINFO_HTTP_CODE);
  if($responseCode==200){
    $jsonObj = json_decode($accessTok_response);
    $accessToken = $jsonObj->{'access_token'};//fetch the access token from the response.
    $refreshToken = $jsonObj->{'refresh_token'};
    $expiresIn = $jsonObj->{'expires_in'};
	      
    $refreshTime=$currentTime+(int)($expiresIn); // Time for token refresh
    $updateTime=$currentTime + ( 24*60*60); // Time to get for a new token update, current time + 24h 
	      
    $fullToken["accessToken"]=$accessToken;
    $fullToken["refreshToken"]=$refreshToken;
    $fullToken["refreshTime"]=$refreshTime;
    $fullToken["updateTime"]=$updateTime;
                        
  }
  else{
    $fullToken["accessToken"]=null;
    $fullToken["errorMessage"]=curl_error($accessTok).$accessTok_response;

			
  }
  curl_close ($accessTok);
  return $fullToken;

}

function GetAccessToken($FQDN,$api_key,$secret_key,$scope){

  $accessTok_Url = $FQDN."/oauth/token";
  //http header values
  $accessTok_headers = array(
			     'Content-Type: application/x-www-form-urlencoded'
			     );

  //Invoke the URL
  $post_data = "client_id=".$api_key."&client_secret=".$secret_key."&scope=".$scope."&grant_type=client_credentials";

  $accessTok = curl_init();
  curl_setopt($accessTok, CURLOPT_URL, $accessTok_Url);
  curl_setopt($accessTok, CURLOPT_HTTPGET, 1);
  curl_setopt($accessTok, CURLOPT_HEADER, 0);
  curl_setopt($accessTok, CURLINFO_HEADER_OUT, 0);
  //  curl_setopt($accessTok, CURLOPT_HTTPHEADER, $accessTok_headers);
  curl_setopt($accessTok, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($accessTok, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($accessTok, CURLOPT_POST, 1);
  curl_setopt($accessTok, CURLOPT_POSTFIELDS,$post_data);
  $accessTok_response = curl_exec($accessTok);

  $responseCode=curl_getinfo($accessTok,CURLINFO_HTTP_CODE);
  $currentTime=time();
  /*
   If URL invocation is successful fetch the access token and store it in session,
   else display the error.
  */
  if($responseCode==200)
    {
      $jsonObj = json_decode($accessTok_response);
      $accessToken = $jsonObj->{'access_token'};//fetch the access token from the response.
      $refreshToken = $jsonObj->{'refresh_token'};
      $expiresIn = $jsonObj->{'expires_in'};
      $refreshTime=$currentTime+(int)($expiresIn); // Time for token refresh
      $updateTime=$currentTime + ( 24*60*60); // Time to get a new token update, current time + 24h

      $fullToken["accessToken"]=$accessToken;
      $fullToken["refreshToken"]=$refreshToken;
      $fullToken["refreshTime"]=$refreshTime;
      $fullToken["updateTime"]=$updateTime;
      
    }else{
 
    $fullToken["accessToken"]=null;
    $fullToken["errorMessage"]=curl_error($accessTok).$accessTok_response;

  }
  curl_close ($accessTok);
  return $fullToken;
}


function SaveToken( $fullToken,$oauth_file ){

  $accessToken=$fullToken["accessToken"];
  $refreshToken=$fullToken["refreshToken"];
  $refreshTime=$fullToken["refreshTime"];
  $updateTime=$fullToken["updateTime"];
      

  $tokenfile = $oauth_file;
  $fh = fopen($tokenfile, 'w');
  $tokenfile="<?php \$accessToken=\"".$accessToken."\"; \$refreshToken=\"".$refreshToken."\"; \$refreshTime=".$refreshTime."; \$updateTime=".$updateTime."; ?>";
  fwrite($fh,$tokenfile);
  fclose($fh);
}

function check_token( $FQDN,$api_key,$secret_key,$scope, $fullToken,$oauth_file){

  $currentTime=time();

  if ( ($fullToken["updateTime"] == null) || ($fullToken["updateTime"] <= $currentTime)){
    $fullToken=GetAccessToken($FQDN,$api_key,$secret_key,$scope);
    if(  $fullToken["accessToken"] == null ){
      //            echo "GetAccessToken".$fullToken["errorMessage"];
    }else{
      //      echo $fullToken["accessToken"];
      SaveToken( $fullToken,$oauth_file );
    }
  }
  elseif ($fullToken["refreshTime"]<= $currentTime){
    $fullToken=RefreshToken($FQDN,$api_key,$secret_key,$scope, $fullToken);
    if(  $fullToken["accessToken"] == null ){
      //      echo "RefreshToken".$fullToken["errorMessage"];
    }else{
      //      echo $fullToken["accessToken"];
      SaveToken( $fullToken,$oauth_file );
    }
  }
  
  return $fullToken;
  
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml" lang="en"><head>
    <title>AT&T Sample Payment Application - Single Pay Application</title>
    <meta content="text/html; charset=ISO-8859-1" http-equiv="Content-Type">
    <link rel="stylesheet" type="text/css" href="style/common.css"/ >

<body>

<div id="container">
<!-- open HEADER --><div id="header">

<div>
    <div id="hcRight">
      <?php echo  date("D M j G:i:s T Y"); ?>
    </div>
    <div id="hcLeft">Server Time:</div>
</div>
<div>
    <div id="hcRight"><script language="JavaScript" type="text/javascript">
var myDate = new Date();
document.write(myDate);
</script></div>
    <div id="hcLeft">Client Time:</div>
</div>
<div>
    <div id="hcRight"><script language="JavaScript" type="text/javascript">
document.write("" + navigator.userAgent);
</script></div>
    <div id="hcLeft">User Agent:</div>
</div>
<br clear="all" />
</div><!-- close HEADER -->

<div id="wrapper">
<div id="content">

<h1>AT&T Sample Payment Application - Single Pay Application</h1>
<h2>Feature 1: Create New Transaction</h2><br/>

</div>
</div>
<form method="post" name="newTransaction" >
<div id="navigation">

<table border="0" width="100%">
  <tbody>
  <tr>
    <td width="50%" valign="top" class="label">Buy product 1 for $0.99:</td>
    <td class="cell"><input type="radio" name="product" value="1" checked>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top" class="label">Buy product 2 for $2.99:</td>
    <td class="cell"><input type="radio" name="product" value="2">
	</td></tr>
  </tbody></table>

</div>
<div id="extra">

  <table>
  <tbody>
  <tr>
  	<td><br /><br /><button type="submit" name="newTransaction" value="newTransaction">Buy Product</button></td>
  </tr>
  </tbody></table>


</div>
<br clear="all" />
<div align="center"></div>
</form>

<?php 
 if($newTransaction!=null) { 
$merchantTrxId = "user".rand(1,10000000)."transaction".rand(1,10000000);
$_SESSION["pay1_merchantTrxId"] =  $merchantTrxId;
$_SESSION["pay1_trxId"] = null;
$_SESSION["pay1_authCode"] =  null;
$_SESSION["pay1_consumerId"] =  null;


$forNotary = "notary.php?signPayload=true&return=singlepay.php&payload={\"Amount\":".$amount.", \"Category\":1, \"Channel\":".
"\"MOBILE_WEB\",\"Description\":\"".$description."\",".
"\"MerchantTransactionId\":\"".$merchantTrxId ."\", \"MerchantProductId\":\"".$merchantProductId."\",".
"\"MerchantPaymentRedirectUrl\":".
"\"".$singlepayRedirect."\"}";
header("location:".$forNotary);
}

if($_REQUEST["TransactionAuthCode"]!=null) { 
$_SESSION["pay1_authCode"] = $_REQUEST["TransactionAuthCode"] ;
?>

<div class="successWide">
<strong>SUCCESS:</strong><br />
<strong>Merchant Transaction ID</strong> <?php echo $_SESSION["pay1_merchantTrxId"] ?><br/>
<strong>Transaction Auth Code</strong> <?php echo $authCode ?><br /><br/>
<form name="getNotaryDetails" action="notary.php">
    <input type="submit" name="getNotaryDetails" value="View Notary Details" />
</form>
</div><br/>
<?php } ?>

<?php
if( $_REQUEST["signedPayload"]!=null && $_REQUEST["signature"]!=null){
    header("location:".$FQDN."/Commerce/Payment/Rest/2/Transactions?clientid=".$api_key."&SignedPaymentDetail=".$_REQUEST["signedPayload"]."&Signature=".$_REQUEST["signature"]);
}
?>


<div id="wrapper">
<div id="content">

<h2><br />
 Feature 2: Get Transaction Status</h2>

<?php if($getTransactionStatus!=null) { 

  //This application uses the Autonomous Client OAuth consumption model
  //Check if there is a valid access token that has not expired
  $fullToken["accessToken"]=$accessToken;
  $fullToken["refreshToken"]=$refreshToken;
  $fullToken["refreshTime"]=$refreshTime;
  $fullToken["updateTime"]=$updateTime;

  $fullToken=check_token($FQDN,$api_key,$secret_key,$scope,$fullToken,$oauth_file);
  $accessToken=$fullToken["accessToken"];

  $getTransactionType = $_POST["getTransactionType"];
  $url = "";
  if($getTransactionType==1)
    $url = $FQDN."/Commerce/Payment/Rest/2/Transactions/MerchantTransactionId/".$merchantTrxId;   
  if($getTransactionType==2)
    $url = $FQDN."/Commerce/Payment/Rest/2/Transactions/TransactionAuthCode/".$authCode;
  if($getTransactionType==3)
    $url = $FQDN."/Commerce/Payment/Rest/2/Transactions/TransactionId/".$trxId;

  $url=$url."?access_token=".$accessToken;

  $headers = array(
		'Accept: application/json'
	);
  $request = curl_init();
  curl_setopt($request, CURLOPT_URL, $url);
  curl_setopt($request, CURLOPT_HTTPGET, 1);
  curl_setopt($request, CURLOPT_HEADER, 0);
  curl_setopt($request, CURLINFO_HEADER_OUT, 0);
  curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);

  $response = curl_exec($request);
  
  $responseCode=curl_getinfo($request,CURLINFO_HTTP_CODE);

  if($responseCode==200) {
    $jsonResponse = json_decode($response,true);
    $trxId = $jsonResponse["TransactionId"];
    $_SESSION["pay1_trxId"] = $trxId;
    $consumerId = $jsonResponse["ConsumerId"];
    $_SESSION["pay1_consumerId"] = $consumerId;
    $merchantTrxId = $jsonResponse["MerchantTransactionId"];
    $_SESSION["pay1_merchantTrxId"]=$merchantTrxId;

    If ( $trxId != null && $trxId != ""){

    $transaction["trxId"] = $trxId;
    $transaction["merchantTrxId"] = $merchantTrxId;
    $transaction["authCode"] = $authCode;
    $transaction["consumerId"] = $consumerId;

      if ( file_exists( $db_filename) ){
	$transactions = unserialize(file_get_contents($db_filename));
	$transaction_exist = false;
	foreach( $transactions as $tr){
	  if($tr["merchantTrxId"] == $merchantTrxId){
	    $transaction_exist = true;
	  }
	}
	if(!$transaction_exist){
	  $stored_tnumber = array_unshift($transactions,$transaction);
	  if ( $stored_tnumber > 5 ){
	    array_pop($transactions);
	  }
	}
      }else{
	$transactions = array($transaction);
      }
      $fp = fopen($db_filename, 'w+') or die("I could not open $filename.");
      fwrite($fp, serialize($transactions));
      fclose($fp);
    }
  }
}
?>
</div>
</div>
 <form method="post" name="getTransactionStatus" action="singlepay.php">
<div id="navigation" align="center">

<table style="width: 750px" cellpadding="1" cellspacing="1" border="0">
<thead>
    <tr>
        <th style="width: 150px" class="cell" align="right"></th>
        <th style="width: 100px" class="cell"></th>
        <th style="width: 240px" class="cell" align="left"></th>
    </tr>
</thead>
  <tbody>
  <tr>
    <td class="cell" align="left">
        <input type="radio" name="getTransactionType" value="1" checked /> Merchant Trans. ID:
    </td>
    <td></td>
    <td class="cell" align="left"><?php echo $merchantTrxId ?></td>
  </tr>
  <tr>
    <td class="cell" align="left">
        <input type="radio" name="getTransactionType" value="2" /> Auth Code:
    <td></td>
    <td class="cell" align="left"><?php echo $authCode ?></td>
    </td>
  </tr>
  <tr>
    <td class="cell" align="left">
         <input type="radio" name="getTransactionType" value="3" /> Transaction ID:
    <td></td>
    <td class="cell" align="left"><?php echo $trxId ?></td>
    </td>
  </tr>
  <tr>
    <td></td>
    <td></td>
    <td></td>
    <td class="cell"><button  type="submit" name="getTransactionStatus" value="getTransactionStatus">Get Transaction Status</button>
    </td>
  </tr>
  </tbody></table>


</form>
</div>
<br clear="all" />
<?php 
if($getTransactionStatus!=null) { 
  if($responseCode==200) {
    ?>

        <div class="successWide">
        <strong>SUCCESS:</strong><br />
	   <strong>Transaction ID:</strong><?php echo $trxId; ?><br />
	   <strong>Merchant Transaction ID:</strong><?php echo $merchantTrxId; ?><br/>
        </div><br/>
        <div align="center"><table style="width: 650px" cellpadding="1" cellspacing="1" border="0">
        <thead>
            <tr>
                <th style="width: 100px" class="cell" align="right"><strong>Parameter</strong></th>
                <th style="width: 100px" class="cell"><strong></strong></th>
                <th style="width: 275px" class="cell" align="left"><strong>Value</strong></th>
            </tr>
        </thead>
        <tbody>
	    <?php 
	       foreach ( $jsonResponse  as $parameter => $value ){ ?>
            	<tr>
		     <td align="right" class="cell"><?php echo $parameter; ?></td>
                    <td align="center" class="cell"></td>
                    <td align="left" class="cell"><?php echo $value; ?></td>
                </tr>
<?php } 
 ?>
        </tbody>
        </table>
        </div><br/>
	    <?php } else { ?>
    <div class="errorWide">
    <strong>ERROR:</strong><br />
<?php     echo curl_error($request).$response;  ?>
    </div><br/>
			   <?php } }?>
<div id="wrapper">
<div id="content">

<h2><br />Feature 3: Refund Transaction</h2>
<?php if($refundTransaction!=null) { 
  //This application uses the Autonomous Client OAuth consumption model
  //Check if there is a valid access token that has not expired
	$fullToken=check_token($FQDN,$api_key,$secret_key,$scope,$fullToken,$oauth_file);
	$accessToken=$fullToken["accessToken"];
	$trxIdRefund =$_REQUEST["trxIdRefund"];

	$url = $FQDN."/Commerce/Payment/Rest/2/Transactions/".$trxIdRefund;
	$url = $url."?access_token=".$accessToken."&Action=refund";

	$headers = array(
			 'Content-Type: application/json',	 
			 'Accept: application/json'
	);
	$payload = "{\"RefundReasonCode\":1,\n \"RefundReasonText\":\"".$refundReasonText."\"}";
	$putData = tmpfile();
	fwrite($putData, $payload);
	fseek($putData, 0);


	$request = curl_init();
	curl_setopt($request, CURLOPT_URL, $url);
	curl_setopt($request, CURLOPT_HTTPGET, 1);
	curl_setopt($request, CURLOPT_HEADER, 0);
	curl_setopt($request, CURLINFO_HEADER_OUT, 0);
	curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($request, CURLOPT_PUT, 1);
	curl_setopt($request, CURLOPT_INFILE, $putData);
	curl_setopt($request, CURLOPT_INFILESIZE, strlen($payload));
	$response = curl_exec($request);
	fclose($putData);

	
	$responseCode=curl_getinfo($request,CURLINFO_HTTP_CODE);
	
	if($responseCode==200) {

	  if ( file_exists( $db_filename) ){
	    $transactions = unserialize(file_get_contents($db_filename));
	    foreach($transactions as $key=>&$transaction){
	      if($transaction["trxId"] == $trxIdRefund){
		   unset($transactions[$key]);
	      }
	    }
	    $fp = fopen($db_filename, 'w+') or die("I could not open $filename.");
	    fwrite($fp, serialize($transactions));
	    fclose($fp);
	  }
	}
    }

?>


</div>
</div>
<form method="post" name="refundTransaction" action="singlepay.php">
<div id="navigation" align="center">

<table style="width: 750px" cellpadding="1" cellspacing="1" border="0">
<thead>
    <tr>
<th style="width: 150px" class="cell" align="right"><strong>Transaction ID</strong></th>
        <th style="width: 100px" class="cell"></th>
<th style="width: 240px" class="cell" align="left"><strong>Merchant Transaction ID</strong></th>
    <td><div class="warning">
<strong>WARNING:</strong><br />
You must use Get Transaction Status to get the Transaction ID before you can refund it.
</div></td>
	</tr>
</thead>
  <tbody>
<?php 
if(true) {
      $transactions = unserialize(file_get_contents($db_filename)); 
      $checked = true;
      foreach ( $transactions as $transaction ){
?>
                      <tr>
                        <td class="cell" align="right">
	                <?php 	if ( $checked ){
	                          $checked = false;
                        ?>
				  <input type="radio" name="trxIdRefund" value="<?php echo $transaction["trxId"]; ?>" checked /><?php echo $transaction["trxId"];?>
				  <?php } else { ?>
                            <input type="radio" name="trxIdRefund" value="<?php echo $transaction["trxId"]; ?>" /><?php echo $transaction["trxId"]; ?>
	    <?php } ?>

                        </td>
                        <td></td>
                        <td class="cell" align="left"><?php echo $transaction["merchantTrxId"] ?></td>
                      </tr>  
<?php 
      } }?>

  <tr>
    <td></td>
    <td></td>
    <td></td>
    <td class="cell"><button  type="submit" name="refundTransaction" value="refundTransaction">Refund Transaction</button>
    </td>
  </tr>
  </tbody></table>


</form>
</div>
<br clear="all" />
<?php 
     if($refundTransaction!=null) { 	
	if($responseCode==200) {
?>
            <div class="successWide">
            <strong>SUCCESS:</strong><br />
            <strong>Transaction ID:</strong> <?php echo $response ?><br />
            </div><br/>
	    <?php } else { ?>
            <div class="errorWide">
            <strong>ERROR:</strong><br />
             <?php echo $response ?><br />
            </div><br/>
<?php } } ?>

<div id="wrapper">
<div id="content">

<h2><br />Feature 4: Notifications</h2>

</div>
</div>
<form method="post" name="refreshNotifications" action="singlepay.php">
<div id="navigation"><br/>

<div align="center"><table style="width: 650px" cellpadding="1" cellspacing="1" border="0">
<thead>
    <tr>
    	<th style="width: 100px" class="cell"><strong>Notification ID</strong></th>
        <th style="width: 100px" class="cell"><strong>Notification Type</strong></th>
        <th style="width: 125px" class="cell"><strong>Transaction ID</strong></th>
        <th style="width: 175px" class="cell"><strong>Merchant Transaction ID</strong></th>
	</tr>
</thead>
<tbody>
<?php 
if(true) {
      $transactions = unserialize(file_get_contents($db_filename)); 
      foreach ( $notifications as $notification ){
?>

	<tr>
	  <td align="center" class="cell"><?php echo $notification["notificationId"]; ?></td>
        <td align="center" class="cell"><?php echo $notification["notificationType"]; ?></td>
        <td align="center" class="cell"><?php echo $notification["transactionId"]; ?></td>
        <td align="center" class="cell"><?php echo $notification["merchantTransactionId"]; ?></td>
    </tr>
<?php } } ?>
</tbody>
</table>
</div>
<div id="extra"><br/>

<table border="0" width="100%">
  <tbody>
  <tr>
    <td class="cell"><button type="submit" name="refreshNotifications" value="refreshNotifications">Refresh</button>
    </td>
  </tr>
  </tbody></table>

</div>
<br clear="all" />
</form></div>

<div id="footer">

	<div style="float: right; width: 20%; font-size: 9px; text-align: right">Powered by AT&amp;T Virtual Mobile</div>
    <p>&#169; 2011 AT&amp;T Intellectual Property. All rights reserved.  <a href="http://developer.att.com/" target="_blank">http://developer.att.com</a>
<br>
The Application hosted on this site are working examples intended to be used for reference in creating products to consume AT&amp;T Services and  not meant to be used as part of your product.  The data in these pages is for test purposes only and intended only for use as a reference in how the services perform.
<br>
For download of tools and documentation, please go to <a href="https://devconnect-api.att.com/" target="_blank">https://devconnect-api.att.com</a>
<br>
For more information contact <a href="mailto:developer.support@att.com">developer.support@att.com</a>

</div>
</div>

</body></html>