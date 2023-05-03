<?php
require_once('config.php');
header('Content-Type: application/json; charset=utf-8');
$hours = '{
    "friday": [
      {
        "end_time": "23:59",
        "start_time": "08:00"
      }
    ],
    "sunday": [
      {
        "end_time": "15:15",
        "start_time": "08:00"
      },
      {
        "end_time": "22:15",
        "start_time": "17:00"
      }
    ],
    "tuesday": [
      {
        "end_time": "23:15",
        "start_time": "08:00"
      }
    ],
    "saturday": [
      {
        "end_time": "19:00",
        "start_time": "08:00"
      }
    ],
    "thursday": [
      {
        "end_time": "01:30",
        "start_time": "20:00"
      }
    ],
    "wednesday": [
      {
        "end_time": "22:00",
        "start_time": "08:00"
      }
    ]
  }';
  $hours = json_decode($hours);
echo json_encode(stardarHour($hours));


