<?php 

if(!empty($_GET['suc']) && $_GET['suc'] == 'OK'){
		$returnurl = $_GET['rurl'];
		$hppRequestId = $_GET['hrid'];
		$referenceId = $_GET['rfid'];
}else{
	die('Wrong Path');
}

?>
<form method="POST" id="waafiformcustom" action="<?php echo $returnurl; ?>">
	<input type="hidden" name="hppRequestId" id="hppRequestId" VALUE="<?php echo $hppRequestId;  ?>" >
	<input type="hidden" name="referenceId" id="referenceId" VALUE="<?php echo $referenceId; ?>" >  
</form>

<script>
	document.getElementById("waafiformcustom").submit();
</script>