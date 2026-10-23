<?php
declare(strict_types=1);
// LMS drivers can return false and accumulate errors rather than throw exceptions.
// Turn new database errors into exceptions so no later action runs on partial data.
final class NotifyDb
{
    private $db;
    public function __construct($db) {$this->db=$db;}
    public function __call(string $method,array $args)
    {
        return $this->checked(function() use($method,$args) {return $this->db->$method(...$args);});
    }
    public function checked(callable $operation)
    {
        $check=method_exists($this->db,'GetErrors');
        $before=$check?count($this->db->GetErrors()):0;
        $result=$operation();
        if($check && count($this->db->GetErrors())>$before) {
            throw new RuntimeException('Błąd bazy danych. Operacja przerwana; szczegóły w dzienniku LMS.');
        }
        return $result;
    }
}
