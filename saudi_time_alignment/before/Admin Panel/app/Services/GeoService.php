<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
class GeoService
{
    public function containing(Builder $query, string $column, float $latitude, float $longitude): Builder
    {
        if (!preg_match('/^[a-z_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid geometry column');
        }
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => $query->whereRaw('ST_Contains(' . $wrapped . ', Point(?, ?))', [$longitude, $latitude]),
            'pgsql' => $query->whereRaw('ST_Contains(' . $wrapped . ', ST_SetSRID(ST_MakePoint(?, ?), ST_SRID(' . $wrapped . ')))', [$longitude, $latitude]),
            default => throw new \LogicException('Unsupported spatial database driver'),
        };
    }
    public function secondsBetween(string $start, string $end): string
    {
        foreach ([$start, $end] as $column) {
            if (!preg_match('/^[a-z_.]+$/', $column)) {
                throw new \InvalidArgumentException('Invalid timestamp column');
            }
        }
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(EPOCH FROM (' . $end . ' - ' . $start . '))',
            'sqlite' => "(julianday({$end}) - julianday({$start})) * 86400",
            default => 'TIMESTAMPDIFF(SECOND, ' . $start . ', ' . $end . ')',
        };
    }
}
