<?php
include 'hmms.php';
$markov=new Markov();
$oString='';
if(isset($_GET['oString'])){
    $oString=$_GET['oString'];
}
$oString=trim($oString);
?>
<html>
<body>
Input:
<form action="" method="get">
    <textarea name="oString" rows="1" cols="100"><?php echo htmlspecialchars($_GET['oString']);?></textarea>
    <input type="submit">
</form>
Output: <?php
if($oString)
echo $markov->tag($oString);?>
</body>

</html>