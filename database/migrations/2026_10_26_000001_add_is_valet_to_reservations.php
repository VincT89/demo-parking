<?php

use App\Integrations\Support\ValetDetector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('is_valet')->nullable();
            $table->index(['parking_id', 'is_valet']);
        });

        /** Use saved payloads only: migrations must not depend on provider availability or change booking timestamps. */
        DB::table('reservations as r')
            ->join('parking_listings as l', 'l.id', '=', 'r.parking_listing_id')
            ->join('platforms as p', 'p.id', '=', 'l.platform_id')
            ->whereIn('p.slug', ['parkos', 'parking-my-car'])
            ->whereNotNull('r.raw_data')
            ->select(['r.id', 'r.raw_data', 'p.slug'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $payload = json_decode($row->raw_data, true);
                    $valet = is_array($payload) ? ValetDetector::fromPayload($row->slug, $payload) : null;
                    if ($valet !== null) {
                        DB::table('reservations')->where('id', $row->id)->whereNull('is_valet')->update(['is_valet' => $valet]);
                    }
                }
            }, 'r.id', 'id');
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['parking_id', 'is_valet']);
            $table->dropColumn('is_valet');
        });
    }
};
