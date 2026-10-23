<?php
declare(strict_types=1);
final class NotifyList
{
    public const COLUMNS=['id','customername','balance','debt','base','percent','state'];
    public static function page(array $rows,string $sort,string $direction,int $page,int $size=50): array
    {
        if(!in_array($sort,self::COLUMNS,true))$sort='id';
        $direction=$direction==='desc'?'desc':'asc';
        $collator=class_exists('Collator')?new Collator('pl_PL'):null;
        usort($rows,static function($a,$b) use($sort,$direction,$collator) {
            $x=$a[$sort]??null;$y=$b[$sort]??null;
            // Unknown percentages always follow numeric percentages, in both directions.
            if($x===null || $y===null)return $x===$y?($a['id']<=>$b['id']):($x===null?1:-1);
            if($sort==='customername')$cmp=$collator?$collator->compare($x,$y):strnatcasecmp($x,$y);
            elseif($sort==='state') {
                $rank=['block'=>0,'notify'=>1,'review'=>2,'ok'=>3];
                $cmp=$rank[$x]<=>$rank[$y];
            }else $cmp=$x<=>$y;
            return $cmp?($direction==='desc'?-$cmp:$cmp):($a['id']<=>$b['id']);
        });
        $total=count($rows);$pages=max(1,(int)ceil($total/$size));$page=max(1,min($page,$pages));
        return ['rows'=>array_slice($rows,($page-1)*$size,$size),'total'=>$total,'pages'=>$pages,'page'=>$page,'sort'=>$sort,'direction'=>$direction];
    }
}
