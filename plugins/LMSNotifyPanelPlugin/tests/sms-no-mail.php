<?php
// Run from the LMS root. All fixture tables are TEMPORARY and shadow production tables
// only in this database session. No messages are sent; the LMS action API is a fake.
if(PHP_SAPI!=='cli') exit(1);
$script_name='NotifyPanel isolated integration tests'; $script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyPanel.php';
$DB->BeginTrans();
try {
    $schema=file_get_contents(__DIR__.'/../sql/install.pgsql.sql');
    $schema=preg_replace('/CREATE TABLE /','CREATE TEMP TABLE ',$schema);
    $schema=preg_replace('/CREATE INDEX .*?;/s','',$schema);
    $DB->Execute($schema);
    $DB->Execute('CREATE TEMP TABLE customers(id integer PRIMARY KEY,name text,lastname text,status integer,deleted integer,cutoffstop bigint,mailingnotice integer,smsnotice integer,divisionid integer DEFAULT 0,notes text DEFAULT \'\',info text DEFAULT \'\')');
    $DB->Execute('CREATE TEMP TABLE customernotes(id serial,customerid integer,dt bigint,message text)');
    $DB->Execute('CREATE TEMP VIEW customeraddressview AS SELECT * FROM customers');
    $DB->Execute('CREATE TEMP TABLE nodes(id integer PRIMARY KEY,ownerid integer,access integer)');
    $DB->Execute('CREATE TEMP TABLE assignments(customerid integer,tariffid integer,liabilityid integer,commited integer DEFAULT 1,suspended integer DEFAULT 0,datefrom bigint DEFAULT 0,dateto bigint DEFAULT 0,period integer DEFAULT 0,id bigserial PRIMARY KEY)');
    $DB->Execute('CREATE TEMP TABLE tariffs(id integer,period integer)');
    $DB->Execute('CREATE TEMP TABLE customercontacts(customerid integer,contact text,type integer)');
    $DB->Execute('CREATE TEMP TABLE cash(id bigserial,customerid integer,value numeric,currencyvalue numeric DEFAULT 1,time bigint DEFAULT 1,docid integer,itemid integer)');
    $DB->Execute('CREATE TEMP TABLE documents(id integer,fullnumber text,cdate bigint,paytime integer)');
    $DB->Execute("INSERT INTO documents VALUES(1,'TEST',1,0)");
    $DB->Execute('CREATE TEMP TABLE invoicecontents(docid integer,itemid integer,period integer)');
    $DB->Execute('INSERT INTO invoicecontents VALUES(1,1,3)');
    ConfigHelper::getConfig('finances.suspension_percentage',0);
    $DB->Execute('CREATE TEMP TABLE uiconfig(section text,var text,value text,disabled integer,userid integer,divisionid integer)');
    $DB->Execute("INSERT INTO uiconfig VALUES('finances','suspension_percentage','0',0,NULL,NULL)");
    $DB->Execute("CREATE TEMP TABLE lnp_sms_delivery(itemid integer PRIMARY KEY,idx varchar(64),number varchar(11),externalid varchar(32),gateway_status varchar(20) DEFAULT 'SUBMITTING',updated_at bigint,report_at bigint DEFAULT 0)");
    $DB->Execute('CREATE TEMP TABLE messageitems(id integer,status integer,lastdate bigint,error text,externalmsgid text)');
    $DB->Execute("INSERT INTO customers VALUES(1,'Test','Klient',3,0,0,1,1)");
    $DB->Execute('INSERT INTO nodes VALUES(11,1,1),(12,1,0)');
    $flag=CONTACT_EMAIL|CONTACT_NOTIFICATIONS;
    $DB->Execute('INSERT INTO customercontacts VALUES(?,?,?),(?,?,?)',[1,'test@example.invalid',$flag,1,'disabled@example.invalid',$flag|CONTACT_DISABLED]);
    $DB->Execute('INSERT INTO assignments(customerid,liabilityid,period) VALUES(1,1,3),(1,2,0)');
    $DB->Execute('INSERT INTO assignments(customerid,liabilityid,period,suspended) VALUES(1,3,3,1)');
    $api=new class($DB) {
        private $db; public $balance=-300; public $monthly=[['currency'=>'PLN','sum'=>100]]; public $deliveries=0; public $lastFiles=null; public $nodeFail=false;public $sendStatus=MSG_SENT;
        public function __construct($db) {$this->db=$db;}
        public function getCustomerBalance($id,$time=null,$expired=false) { if($expired!==false) throw new RuntimeException('Must use total balance'); if((float)$this->db->GetOne('SELECT COALESCE(sum(value),0) FROM cash WHERE customerid=?',[$id])!== (float)$this->balance){$this->db->Execute('DELETE FROM cash WHERE customerid=?',[$id]); $this->db->Execute('INSERT INTO cash(customerid,value,docid,itemid) VALUES(?,?,1,1)',[$id,$this->balance]);} return $this->balance; }
        public function GetCustomerAssignments($id,$expired=false,$approved=true) {
            if(!$this->monthly)return [];
            $sum=$this->monthly[0]['sum'];
            return array_map(static function($r) use($sum) {return array_merge($r,['periodvalue'=>$r['period'],'currency'=>'PLN','discounted_value'=>$r['suspended']?0:$sum]);},$this->db->GetAll('SELECT * FROM assignments WHERE customerid=?',[$id])?:[]);
        }
        public function SuspendAssignment($id,$suspend=true) {$this->db->Execute('UPDATE assignments SET suspended=? WHERE id=?',[$suspend?1:0,$id]);}
        public function NodeSet($id,$access) {if($this->nodeFail)throw new RuntimeException('Simulated node failure');$this->db->Execute('UPDATE nodes SET access=? WHERE id=?',[$access,$id]);}
        public function addMessage($p) {
            $contact=$p['recipients'][0]['email']??$p['recipients'][0]['phone'];
            if(!in_array($contact,['test@example.invalid','48600000000'],true)) throw new RuntimeException('Disabled contact selected');
            $this->db->Execute('INSERT INTO messageitems(id,status) VALUES(1,3)'); return ['id'=>99,'items'=>[1=>[$contact=>1]]];
        }
        public function SendSMS($number,$body,$item,$options=null) {++$this->deliveries;if(empty($options['lnp_manual']))throw new LogicException('Missing manual flag');return MSG_SENT;}
        public function SendMail($destination,$headers,$body,$files=null) {++$this->deliveries;$this->lastFiles=$files; return $this->sendStatus;}
    };
    $np=new NotifyPanel($DB,$api,1);$s=$np->settings();
    function must($ok,$name){if(!$ok)throw new RuntimeException($name);echo 'PASS '.$name.PHP_EOL;}
    $DB->Execute('UPDATE customers SET divisionid=1 WHERE id=1');
    $DB->Execute('INSERT INTO customercontacts VALUES(?,?,?)',[1,'48600000000',CONTACT_MOBILE|CONTACT_NOTIFICATIONS]);
    $api->balance=-199.99;
    foreach(['notify_sms','notify_sms_no_mail'] as $action){try{$np->act(1,$action,$s['revision']);throw new LogicException('Below-threshold SMS accepted');}catch(RuntimeException $e){must($api->deliveries===0,'SMS below 200 percent rejected before send: '.$action);}}
    $api->balance=-200;
    $r=$np->act(1,'notify_sms_no_mail',$s['revision'],$np->smsPreviewHash($np->inspect(1),$s));must($api->deliveries===0 && strpos($r,'klient ma wpisany')!==false,'email owner excluded at execution');
    $DB->Execute('UPDATE customercontacts SET contact=?,type=? WHERE contact=?',['invalid-email',CONTACT_EMAIL|CONTACT_DISABLED,'test@example.invalid']);
    must($np->hasEmailContact(1),'disabled invalid but nonempty email conservatively excluded');
    $DB->Execute('DELETE FROM customercontacts WHERE (type & ?)=?',[CONTACT_EMAIL,CONTACT_EMAIL]);
    $hash=$np->smsPreviewHash($np->inspect(1),$s);
    $DB->Execute('UPDATE customercontacts SET contact=? WHERE contact=?',['48600000001','48600000000']);
    try{$np->act(1,'notify_sms_no_mail',$s['revision'],$hash);throw new LogicException('Changed recipient accepted');}catch(RuntimeException $e){must($api->deliveries===0,'recipient changed after preview prevents delivery');}
    $DB->Execute('UPDATE customercontacts SET contact=? WHERE contact=?',['48600000000','48600000001']);
    $r=$np->act(1,'notify_sms_no_mail',$s['revision'],$np->smsPreviewHash($np->inspect(1),$s));must($api->deliveries===1 && strpos($r,'Kanały przyjęte')===0,'manual SMS-only send uses its own channel despite mail settings');
    $r=$np->act(1,'notify_sms_no_mail',$s['revision'],$np->smsPreviewHash($np->inspect(1),$s));must($api->deliveries===1,'SMS repeat interval prevents duplicate');
    $DB->Execute('DELETE FROM lnp_history');
    $DB->Execute('INSERT INTO customercontacts VALUES(?,?,?)',[1,'new@example.invalid',CONTACT_EMAIL]);
    $r=$np->act(1,'notify_sms_no_mail',$s['revision'],$np->smsPreviewHash($np->inspect(1),$s));must($api->deliveries===1,'email added after preview prevents send');
    $DB->Execute('DELETE FROM customercontacts WHERE (type & ?)=?',[CONTACT_EMAIL,CONTACT_EMAIL]);
    $DB->Execute('UPDATE customers SET smsnotice=0 WHERE id=1');
    $r=$np->act(1,'notify_sms_no_mail',$s['revision'],$np->smsPreviewHash($np->inspect(1),$s));must($api->deliveries===1,'SMS consent respected');
    try{$np->act(1,'notify_sms_no_mail',$s['revision'],null,true);throw new LogicException('Automatic accepted');}catch(RuntimeException $e){must($api->deliveries===1,'automatic mode cannot invoke manual SMS');}
    $DB->Execute('UPDATE customers SET smsnotice=1 WHERE id=1');
    $DB->Execute('INSERT INTO customercontacts VALUES(?,?,?)',[1,'new@example.invalid',CONTACT_EMAIL]);
    $DB->Execute('DELETE FROM lnp_history');$DB->Execute('DELETE FROM lnp_sms_delivery');
    $hash=$np->smsPreviewHash($np->inspect(1),$s);
    $r=$np->act(1,'notify_sms',$s['revision'],$hash,false,true);
    must($api->deliveries===2,'customer card SMS works independently of email contact');
    must($DB->GetOne('SELECT origin FROM lnp_block_notices WHERE customerid=1')==='manual','shared customer card checkbox starts existing manual countdown');
    echo 'No external deliveries; temporary tables only.'.PHP_EOL;
}finally{$DB->RollbackTrans();}
