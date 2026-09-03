<?php

return [
    'province' => [
        ['citiesProvinceCode' => '110000', 'citiesProvinceName' => '北京市'],
        ['citiesProvinceCode' => '310000', 'citiesProvinceName' => '上海市'],
        ['citiesProvinceCode' => '440000', 'citiesProvinceName' => '广东省'],
    ],
    'city' => [
        ['citiesCityCode' => '110100', 'citiesCityName' => '北京市', 'citiesProvinceCode' => '110000'],
        ['citiesCityCode' => '310100', 'citiesCityName' => '上海市', 'citiesProvinceCode' => '310000'],
        ['citiesCityCode' => '440100', 'citiesCityName' => '广州市', 'citiesProvinceCode' => '440000'],
        ['citiesCityCode' => '440300', 'citiesCityName' => '深圳市', 'citiesProvinceCode' => '440000'],
    ],
    'county' => [
        ['citiesCountyCode' => '110101', 'citiesCountyName' => '东城区', 'citiesCityCode' => '110100'],
        ['citiesCountyCode' => '310101', 'citiesCountyName' => '黄浦区', 'citiesCityCode' => '310100'],
        ['citiesCountyCode' => '440103', 'citiesCountyName' => '荔湾区', 'citiesCityCode' => '440100'],
        ['citiesCountyCode' => '440305', 'citiesCountyName' => '南山区', 'citiesCityCode' => '440300'],
    ],
];
