<?
function makeUniqueAndRenumberKeys($arrayValue){
	if ($arrayValue==null){
		return null;
	}
	$arrayValue = array_unique($arrayValue);
	return array_values($arrayValue);
}

$addressesRaw = isset($_POST["addresses"])?
					$_POST["addresses"]:
					(isset($_GET["addresses"])?
						$_GET["addresses"]:
						"");
$addressesRaw = preg_replace("/[^A-Za-z0-9,]/", "", $addressesRaw);
$addressesRaw = preg_replace("/,/", ",\n", $addressesRaw);

$outGraphCode="";

if ($addressesRaw){

	$addresses = trim($addressesRaw);
	$addresses = preg_split('/,/', $addresses);

	$givenAddresses=null;
	foreach ($addresses as $address){
		$givenAddresses[]=trim($address);
	}

	$addressesInfo=null;
	foreach($givenAddresses as $key => $address){

		$jsonStr = @file_get_contents(
			"http://blockchain.info/address/$address?format=json"
			);

		if (!$jsonStr){
		
			echo "Error getting data for: $address (Invalid address?)<br />";
		
		}else{

			$fullInfo = json_decode($jsonStr);
			$transactions = $fullInfo->txs;
		
			$addressesInfo[$address]=null;
			$addressesInfo[$address]["inputs"]=null;
			$addressesInfo[$address]["outputs"]=null;

			foreach($transactions as $transaction){
				$inputs = $transaction->inputs;
				$outputs = $transaction->out;

				foreach($inputs as $input){
					$inAddr=$input->prev_out->addr;
					if ($inAddr!=$address){
						$addressesInfo[$address]["inputs"][]=$inAddr;
					}
				}

				foreach($outputs as $output){
					$outAddr=$output->addr;
					if ($outAddr!=$address){
						$addressesInfo[$address]["outputs"][]=$outAddr;
					}
				}

				$addressesInfo[$address]["inputs"] = 
					makeUniqueAndRenumberKeys($addressesInfo[$address]["inputs"]);
				$addressesInfo[$address]["outputs"] = 
					makeUniqueAndRenumberKeys($addressesInfo[$address]["outputs"]);

			}//if (!$jsonStr)
		}
	}

	//$addressesInfo shows all input and output addresses used by each address. 
	//These are seperated by address.
	//Now we will merge all these so we just see for each address, who it sent to. 

	$sentFromThisAddress=null;
	foreach($addressesInfo as $address => $addressInfo){

		foreach($addressInfo["inputs"] as $inputAddr){
			$sentFromThisAddress[$inputAddr][]=$address;
		}
		foreach($addressInfo["outputs"] as $outputAddr){
			$sentFromThisAddress[$address][]=$outputAddr;
		}
	}

	//Now we have every address in the system (beyond those given), and where they send to.
	//Now we can generate the graph 

	$edges=null;
	$allAddresses=null; //Starts with this
	foreach($sentFromThisAddress as $fromAddress => $toAddresses){
		foreach ($toAddresses as $toAddress) {
			$fromAddress2=substr($fromAddress,0,3)."...".substr($fromAddress,-3);
			$toAddress2=substr($toAddress,0,3)."...".substr($toAddress,-3);

			//echo "$fromAddress -> $toAddress<br />";
			$edges[]= "$fromAddress -> $toAddress\n";
			$allAddresses[]=$fromAddress;
			$allAddresses[]=$toAddress;			
		}
	}
	$allAddresses=makeUniqueAndRenumberKeys($allAddresses);

	$outGraphCode="";
	foreach($edges as $edge){
		$outGraphCode.= $edge;
	}

	//Labels
	foreach($allAddresses as $key => $address){
		$address2=substr($address,0,3)."...".substr($address,-3);
		$color=null;
		if (false !== array_search($address, $givenAddresses)){
			$color="color:#0C5AA6,";
		}else{
			$color="color:#FF9700,";
		}
		$outGraphCode.=$address." {".$color."label:$address2}\n";
	}

}

$graphFileContent=($outGraphCode)?$outGraphCode:"";
?>
<html>
<head>
	<title>BitVis - Graph Bitcoin Addresses (Blockchain analysis)</title>
</head>
<body>


<table style="width:100%">
	<tr>
		<td rowspan="2" width="800">
			<!--Graph-->
			<canvas id="viewport" width="800" height="600"></canvas>

		</td>
		<td>
			<div style="width:100%;text-align:right"><small><a href="./">Restart</a> <a href="https://github.com/jonwaller/bitvis">Source</a></small></div>
			<!--Address input-->
			Bitcoin Addresses: (Comma separated)

			<form method="get">

<textarea name="addresses" rows="10" style="width:100%;height:100%">
<?if ($addressesRaw){?>
<?=$addressesRaw?>
<?}else{?>
1K8ZCd8xpbKZXj2QotFSzPGrgb1YNQV1yT,
18AFeTEXJKY3ueWMMDhKKcNLvrHR8sv17y,
1B8KdKBHkhHaRP7a7ioTAErybTfRGsETGc
<?}?>
</textarea><br />

			<input type="submit" value="Build new graph"/>
			</form>
		</td>
	</tr>
	<?if($graphFileContent){?>
		<tr>
			<td>
				<!--Graph file-->
				<textarea id="code" rows="30" style="width:100%;height:100%">
<?=$graphFileContent?>
				</textarea>
				<br />
				<small>Editing this will update the graphic.</small>
			</td>
		</tr>
	<?}?>
</table>

<script src="lib/jquery-1.6.1.min.js"></script>
<script src="lib/jquery.address-1.4.min.js"></script>

<script src="lib/arbor.js"></script>
<script src="lib/arbor-tween.js"></script>
<script src="lib/graphics.js"></script>

<script src="src/parseur.js"></script>
<script src="src/renderer.js"></script>

<script>
	trace = arbor.etc.trace
	objmerge = arbor.etc.objmerge
	objcopy = arbor.etc.objcopy
	var parse = Parseur().parse

    var sys = arbor.ParticleSystem()
    sys.parameters({stiffness:900, repulsion:2000, gravity:false, dt:0.015})
    sys.renderer = Renderer("#viewport");

    function updateGraph(){

	    var codeStr=$("#code").val();
	    if (codeStr != undefined){

	        var networkData = parse(codeStr)
		    sys.graft(networkData);
		}
	}

	updateGraph();
	$("#code").keyup(updateGraph);
</script>

</body>
</html>