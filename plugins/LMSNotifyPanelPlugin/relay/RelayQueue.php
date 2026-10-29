<?php
declare(strict_types=1);
if(!class_exists('NotifySmsRelayProtocol',false))require_once __DIR__.'/NotifySmsRelayProtocol.php';
final class RelayQueue
{
    private PDO $db;
    public function __construct(string $path)
    {
        $this->db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        chmod($path,0600);$this->db->exec('PRAGMA busy_timeout=5000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS reports(id TEXT PRIMARY KEY,body TEXT NOT NULL,created_at INTEGER NOT NULL,acked_at INTEGER NOT NULL DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS nonces(id TEXT PRIMARY KEY,created_at INTEGER NOT NULL)');
    }
    public function store(array $reports): void
    {
        $this->db->beginTransaction();
        try{
            if((int)$this->db->query('SELECT count(*) FROM reports WHERE acked_at=0')->fetchColumn()>10000)throw new RuntimeException('Queue full');
            $q=$this->db->prepare('INSERT OR IGNORE INTO reports(id,body,created_at) VALUES(?,?,?)');
            foreach($reports as $r){$body=json_encode(NotifySmsRelayProtocol::record($r),JSON_THROW_ON_ERROR);$q->execute([hash('sha256',$body),$body,time()]);}
            $this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public function authorize(string $op,string $timestamp,string $nonce,string $body,string $signature,string $secret): bool
    {
        if(!ctype_digit($timestamp) || abs(time()-(int)$timestamp)>120 || !preg_match('/^[a-f0-9]{32}$/D',$nonce) || !preg_match('/^[a-f0-9]{64}$/D',$signature) || !hash_equals(NotifySmsRelayProtocol::signature($op,$timestamp,$nonce,$body,$secret),$signature))return false;
        $cleanup=$this->db->prepare('DELETE FROM nonces WHERE created_at<?');$cleanup->execute([time()-300]);
        $q=$this->db->prepare('INSERT OR IGNORE INTO nonces(id,created_at) VALUES(?,?)');$q->execute([$nonce,time()]);
        return $q->rowCount()===1;
    }
    public function pull(): array
    {
        $rows=$this->db->query('SELECT id,body,created_at FROM reports WHERE acked_at=0 ORDER BY created_at,rowid LIMIT 25')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn($r)=>['key'=>$r['id'],'received_at'=>(int)$r['created_at'],'report'=>json_decode($r['body'],true,512,JSON_THROW_ON_ERROR)],$rows);
    }
    public function ack(array $keys): int
    {
        if(count($keys)>25)throw new InvalidArgumentException('Too many acknowledgements');
        foreach($keys as $key)if(!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Invalid acknowledgement');
        $q=$this->db->prepare('UPDATE reports SET acked_at=? WHERE id=? AND acked_at=0');$count=0;
        foreach($keys as $key){$q->execute([time(),$key]);$count+=$q->rowCount();}
        $q=$this->db->prepare('DELETE FROM reports WHERE acked_at>0 AND acked_at<?');$q->execute([time()-7*86400]);
        return $count;
    }
}
