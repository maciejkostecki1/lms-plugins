<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('CONTACT_EMAIL',1);define('CONTACT_MOBILE',2);define('CONTACT_NOTIFICATIONS',4);define('CONTACT_DISABLED',8);
require_once __DIR__.'/../lib/NotifyPanel.php';
$db=new class {
    public int $queries=0;
    public function GetRow($sql,$args=[]){++$this->queries;return ['payload'=>'{}','revision'=>1];}
    public function GetAll($sql,$args=[]){
        ++$this->queries;
        if(strpos($sql,'FROM customers WHERE id IN')!==false)return array_map(static fn($id)=>['id'=>$id,'notes'=>'Fixture','info'=>'Info'],$args[0]);
        if(strpos($sql,'FROM customercontacts')!==false)return array_map(static fn($id)=>['customerid'=>$id,'contact'=>'fixture@example.invalid','type'=>5],$args[0]);
        return [];
    }
};
$rows=[];for($id=1;$id<=50;++$id)$rows[]=['id'=>$id,'debt'=>100,'debt_cycle'=>'fixture','mailingnotice'=>true,'smsnotice'=>false];
$out=(new NotifyPanel($db,new stdClass()))->customerListDetails($rows);
if(count($out)!==50 || $db->queries>8)throw new RuntimeException('Customer details query count grew with page size');
foreach($out as $row)if($row['customer_notes']!=='Fixture' || !$row['mail_ready'])throw new RuntimeException('Batch reader differs from contact rules');
echo 'PASS 50 customer details loaded in '.$db->queries.' queries without per-customer lookups.'.PHP_EOL;
