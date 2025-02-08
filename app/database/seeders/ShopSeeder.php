<?php

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // TODO : 日曜始まり
        $businessDays = [
            ['0','0','0','0','1','0','0'],
            ['0','0','0','0','1','0','0'],
            ['0','0','0','0','1','0','0'],
            ['0','0','0','0','1','0','0'],
            ['0','0','0','0','1','0','0'],
        ];
        $businessHour = [
            'start_time'=>'21:00',
            'end_time'=>'24:00',
            'last_order' => '23:30',
        ];
        $notch = [
            '30', '50'
        ];
        Shop::factory()->create([
            'name' => '竜胆ーRINDOUー',
            'title' => '今宵素敵な夜を貴方と',
            'x_id' => 'ff14_rindou',
            'detail' => '当店の名前には
悲しむ貴方に寄り添う
そんな言葉が込められています
悲しいときも貴方に寄り添う
癒しの場所を
どうぞ心行くまでお楽しみください',
            'logo' => null,
            'favicon' => null,
            'address1' => 'GAIA DC',
            'address2' => 'Ultima 鯖',
            'address3' => 'シロガネ 1-37',
            'nearest' => '【拡張街】シロガネ南西',
            'is_sunday_start' => true,
            'business_hour_text' => '21時~24時迄',
            'business_day_text' => '毎週木曜日',
            'last_order_text' => 'ラストオーダー23時半',
            'business_days' => json_encode($businessDays),
            'business_hour' => json_encode($businessHour),
            'last_order' => $businessHour['last_order'],
            'notch' => json_encode($notch),
        ]);
    }
}
