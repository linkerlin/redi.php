<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * RGeo 地理空间数据结构使用示例
 * 
 * RGeo 提供了基于Redis Geo命令的地理空间数据操作能力，
 * 支持坐标存储、距离计算、范围搜索等功能。
 */

// 创建客户端
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

// 连接到Redis
if (!$client->connect()) {
    die("无法连接到Redis服务器\n");
}

echo "=== RGeo 地理空间数据示例 ===\n\n";

// 创建RGeo实例
$geo = $client->getGeo('example:geo');
$geo->clear();

// 场景1: 城市坐标管理
echo "1. 城市坐标管理\n";
echo "---------------\n";

$cities = [
    '北京' => [116.4074, 39.9042],
    '上海' => [121.4737, 31.2304],
    '广州' => [113.2644, 23.1291],
    '深圳' => [114.0579, 22.5431],
    '成都' => [104.0665, 30.5723],
    '杭州' => [120.1551, 30.2741],
    '武汉' => [114.3055, 30.5928],
    '西安' => [108.9402, 34.3416],
    '重庆' => [106.5516, 29.5630],
    '天津' => [117.2009, 39.0842]
];

echo "添加主要城市坐标:\n";
foreach ($cities as $city => $coords) {
    $geo->add($city, $coords[0], $coords[1]);
    echo "  - $city: 经度 {$coords[0]}, 纬度 {$coords[1]}\n";
}

echo "\n已存储城市数量: " . $geo->size() . "\n\n";

// 场景2: 距离计算
echo "2. 距离计算\n";
echo "-----------\n";

$cityPairs = [
    ['北京', '上海'],
    ['广州', '深圳'],
    ['成都', '重庆'],
    ['武汉', '西安']
];

echo "主要城市间距离:\n";
foreach ($cityPairs as [$city1, $city2]) {
    $distanceKm = $geo->distance($city1, $city2, 'km');
    $distanceM = $geo->distance($city1, $city2, 'm');
    echo "  - $city1 到 $city2: " . round($distanceKm, 1) . " 公里 (" . round($distanceM) . " 米)\n";
}

echo "\n";

// 场景3: 地理哈希获取
echo "3. 地理哈希\n";
echo "------------\n";

$sampleCities = ['北京', '上海', '广州'];
echo "城市地理哈希:\n";
foreach ($sampleCities as $city) {
    $hash = $geo->hash($city);
    echo "  - $city: $hash\n";
}

echo "\n";

// 场景4: 坐标查询
echo "4. 坐标查询\n";
echo "------------\n";

echo "城市坐标查询:\n";
foreach ($sampleCities as $city) {
    $position = $geo->position($city);
    if ($position) {
        echo "  - $city: 经度 {$position[0]}, 纬度 {$position[1]}\n";
    }
}

echo "\n";

// 场景5: 范围搜索
echo "5. 范围搜索\n";
echo "------------\n";

// 以北京为中心，搜索500公里内的城市
$beijingCoords = $cities['北京'];
echo "以北京为中心，500公里范围内的城市:\n";
$nearbyCities500 = $geo->radius($beijingCoords[0], $beijingCoords[1], 500, 'km');
foreach ($nearbyCities500 as $city) {
    $distance = $geo->distance('北京', $city, 'km');
    echo "  - $city (距离: " . round($distance, 1) . " 公里)\n";
}

echo "\n以北京为中心，1000公里范围内的城市:\n";
$nearbyCities1000 = $geo->radius($beijingCoords[0], $beijingCoords[1], 1000, 'km');
foreach ($nearbyCities1000 as $city) {
    if (!in_array($city, $nearbyCities500)) {
        $distance = $geo->distance('北京', $city, 'km');
        echo "  - $city (距离: " . round($distance, 1) . " 公里)\n";
    }
}

echo "\n";

// 场景6: 按成员搜索
echo "6. 按成员搜索\n";
echo "--------------\n";

echo "以上海为中心，800公里范围内的城市:\n";
$nearbyShanghai = $geo->radiusByMember('上海', 800, 'km');
foreach ($nearbyShanghai as $city) {
    if ($city !== '上海') {
        $distance = $geo->distance('上海', $city, 'km');
        echo "  - $city (距离: " . round($distance, 1) . " 公里)\n";
    }
}

echo "\n";

// 场景7: 成员管理
echo "7. 成员管理\n";
echo "------------\n";

// 批量添加更多地点
$morePlaces = [
    '青岛' => [120.3826, 36.0671],
    '大连' => [121.6147, 38.9140],
    '厦门' => [118.0894, 24.4798]
];

echo "添加更多地点:\n";
$geo->addAll($morePlaces);
foreach ($morePlaces as $place => $coords) {
    echo "  - $place: 经度 {$coords[0]}, 纬度 {$coords[1]}\n";
}

echo "\n当前总地点数: " . $geo->size() . "\n";

// 删除地点
echo "删除天津...\n";
$geo->remove('天津');
echo "删除后地点数: " . $geo->size() . "\n";

// 获取所有成员
echo "\n所有地理空间成员:\n";
$allMembers = $geo->getMembers();
sort($allMembers);
foreach ($allMembers as $member) {
    echo "  - $member\n";
}

echo "\n";

// 场景8: 实际应用场景 - 附近商家搜索
echo "8. 实际应用 - 附近商家搜索\n";
echo "----------------------------\n";

$businessGeo = $client->getGeo('example:businesses');
$businessGeo->clear();

// 添加商家位置
$businesses = [
    '星巴克(中关村店)' => [116.3180, 39.9845],
    '肯德基(五道口店)' => [116.3376, 39.9921],
    '麦当劳(西单店)' => [116.3735, 39.9078],
    '必胜客(王府井店)' => [116.4134, 39.9097],
    '海底捞(朝阳门店)' => [116.4328, 39.9289]
];

$businessGeo->addAll($businesses);
echo "添加商家位置:\n";
foreach ($businesses as $business => $coords) {
    echo "  - $business: 经度 {$coords[0]}, 纬度 {$coords[1]}\n";
}

// 模拟用户位置搜索附近商家
$userLocation = [116.3500, 39.9800]; // 用户当前位置
echo "\n用户当前位置: 经度 {$userLocation[0]}, 纬度 {$userLocation[1]}\n";

echo "\n2公里范围内的商家:\n";
$nearbyBusinesses = $businessGeo->radius($userLocation[0], $userLocation[1], 2, 'km');
foreach ($nearbyBusinesses as $business) {
    $distance = $businessGeo->distance('用户位置', $business, 'm');
    echo "  - $business (距离: " . round($distance) . " 米)\n";
}

echo "\n5公里范围内的商家:\n";
$widerBusinesses = $businessGeo->radius($userLocation[0], $userLocation[1], 5, 'km');
foreach ($widerBusinesses as $business) {
    if (!in_array($business, $nearbyBusinesses)) {
        $distance = $businessGeo->distance('用户位置', $business, 'km');
        echo "  - $business (距离: " . round($distance, 1) . " 公里)\n";
    }
}

echo "\n";

// 场景9: 错误处理和边界情况
echo "9. 错误处理\n";
echo "-----------\n";

// 测试不存在的位置
$nonExistent = $geo->position('不存在的城市');
echo "不存在的城市查询结果: " . ($nonExistent === null ? 'null' : '存在') . "\n";

// 测试距离计算中不存在的城市
$invalidDistance = $geo->distance('北京', '不存在的城市', 'km');
echo "无效距离查询结果: " . ($invalidDistance === false ? 'false' : $invalidDistance) . "\n";

// 测试存在性
echo "北京是否存在: " . ($geo->exists() ? '是' : '否') . "\n";

// 清空数据
echo "\n清空商家数据...\n";
$businessGeo->clear();
echo "清空后商家数量: " . $businessGeo->size() . "\n";

// 清理数据
echo "\n清理示例数据...\n";
$geo->clear();
$businessGeo->clear();

// 关闭连接
$client->shutdown();
echo "连接已关闭\n";

echo "\n=== RGeo 示例完成 ===\n";
echo "RGeo 适用于以下场景:\n";
echo "- 附近商家/服务搜索\n";
echo "- 物流配送路径规划\n";
echo "- 地理位置推荐系统\n";
echo "- 城市间距离计算\n";
echo "- 地理围栏应用\n";
echo "优点：查询速度快，支持复杂地理运算，内存效率高\n";