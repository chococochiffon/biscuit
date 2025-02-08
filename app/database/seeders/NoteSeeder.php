<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\Shop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Note::factory()->create([
            'shop_id' => Shop::query()->get()->first()->id,
            'note'=>'壱【敷地内に到着されましたら、徒歩モードにしてミニオンを
帰還させ、武器は非表示にしていただくようお願いいたします。

弐【戦闘アクションの使用や、ログを表示した状態での
エモート連打はお控えください。

参【営業中のフレンド申請、FC、LS等への勧誘はご遠慮願います。

肆【キャストへの過度な暴力発言、性的発言、中傷発言等の
センシティブな発言はお控えください。

伍【キャスト、スタッフとの賭け事や、動画配信は禁止して
おります。そのような迷惑行為が発覚した場合、
即退店していただきます。'
        ]);
    }
}
