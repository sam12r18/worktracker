<?php
function eq($a,$b,$label){if($a!==$b){fwrite(STDERR,"FAIL $label: ".var_export($a,true)." != ".var_export($b,true)."\n");exit(1);}echo "PASS $label\n";}
function rate(int $base,float $customer,float $project):int{return (int)round($base*$customer*$project);}
function amount(int $hourly,int $seconds):int{return (int)round($hourly*($seconds/3600));}
function overlapSeconds(int $start,int $end,int $from,int $to):int{return max(0,min($end,$to)-max($start,$from));}

eq(rate(400000,1.10,1.20),528000,'multipliers multiply');
eq(amount(528000,1200),176000,'20 minute billing');
eq(amount(400000,1200)+amount(300000,1200),233333,'concurrent lines remain additive');
eq(overlapSeconds(50,110,100,200),10,'invoice period clips activity without changing source');
$history=[['at'=>0,'value'=>400000],['at'=>200,'value'=>500000]];
$resolve=function(int $at)use($history){$v=null;foreach($history as $r)if($r['at']<=$at)$v=$r['value'];return $v;};
eq($resolve(150),400000,'historical rate before change');eq($resolve(250),500000,'historical rate after change');
echo "alpha.6 invariants PASS\n";
