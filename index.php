<?php
// Автоматически получаем список PDF из папки charts.
// Directory listing в браузере не используется, поэтому 403 на /charts/ не мешает работе.
$chartsDir = __DIR__ . DIRECTORY_SEPARATOR . 'charts';
$asocDir = __DIR__ . DIRECTORY_SEPARATOR . 'asoc';
$serverCharts = [];

if (is_dir($chartsDir)) {
    foreach (scandir($chartsDir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $fullPath = $chartsDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($fullPath)) continue;
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') continue;

        $base = pathinfo($file, PATHINFO_FILENAME);
        if (!preg_match('/^[A-Za-z0-9]{4}$/', $base)) continue;

        $code = strtoupper($base);
        $hasAsoc = false;
        foreach ([$base . '.txt', $code . '.txt', strtolower($base) . '.txt'] as $asocFile) {
            if (is_file($asocDir . DIRECTORY_SEPARATOR . $asocFile)) {
                $hasAsoc = true;
                break;
            }
        }

        $serverCharts[] = [
            'name' => $file,
            'code' => $code,
            'hasAsoc' => $hasAsoc,
            'pdfUrl' => './charts/' . rawurlencode($file),
            'asocUrl' => $hasAsoc ? './asoc/' . rawurlencode($code . '.txt') : null
        ];
    }
}

usort($serverCharts, function ($a, $b) {
    return strcmp($a['code'], $b['code']);
});
?>
<!DOCTYPE html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SkyChartsViewer</title>


    <!-- PDF.js -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>


    <style>

        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;

            font-family: Arial, sans-serif;

            background: #1e3344;
        }


        /* =====================================
           ОСНОВНОЙ КОНТЕЙНЕР
           ===================================== */

        .app {
            display: flex;

            width: 100%;
            height: 100vh;

            overflow: hidden;

            background: #1e3344;
        }


        /* =====================================
           НАЧАЛЬНЫЙ ЭКРАН ВЫБОРА АЭРОПОРТА
           ===================================== */

        .app.initial {
            display: block;
            position: relative;
            background: #1e3344;
        }

        .app.initial .sidebar {
            width: 100%;
            min-width: 0;
            height: 100vh;
            border-right: 0;
            background: #1e3344;
            display: block;
        }

        .app.initial .sidebar-header,
        .app.initial #pageBlock,
        .app.initial .viewer {
            display: none !important;
        }

        .app.initial #uploadBlock {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: min(520px, calc(100% - 40px));
            padding: 0;
            background: transparent;
            z-index: 10;
        }

        .app.initial .airport-label {
            margin-bottom: 8px;
            color: #d7e0e5;
            text-align: center;
            font-size: 13px;
        }

        .app.initial .airport-search-box {
            width: 100%;
        }

        .app.initial #airportSuggestions {
            margin-top: 6px;
            max-height: 360px;
            overflow-y: auto;
            box-shadow: 0 5px 18px rgba(0,0,0,0.35);
        }

        .app.initial .airport-result {
            min-height: 48px;
            padding: 0 16px;
            border-bottom: 1px solid #ddd;
            justify-content: flex-start;
        }

        .app.initial .airport-result:last-child {
            border-bottom: 0;
        }

        .app.initial .airport-result-icon,
        .app.initial .airport-result-name,
        .app.initial .airport-enter-button {
            display: none;
        }

        .app.initial .airport-result-main {
            display: block;
        }

        .app.initial .airport-result-code {
            font-size: 17px;
            color: #111;
        }


        /* =====================================
           БОКОВАЯ ПАНЕЛЬ
           ===================================== */

        .sidebar {
            width: 310px;
            min-width: 310px;

            height: 100%;

            background: #203748;

            border-right: 1px solid #142633;

            display: flex;
            flex-direction: column;

            overflow: hidden;
        }


        /* =====================================
           ЗАГОЛОВОК
           ===================================== */

        .sidebar-header {
            padding: 18px 15px 14px 15px;

            flex-shrink: 0;

            border-bottom: 1px solid #536b7a;

            background: #172936;
        }


        .sidebar h1 {
            margin: 0;

            font-size: 21px;

            color: #f0f0f0;
        }


        /* =====================================
           ВЫБОР PDF
           ===================================== */

        #uploadBlock {
            display: block;

            padding: 15px;

            background: #203748;

            color: #d7e0e5;
        }


        .airport-label {
            margin-bottom: 6px;
            font-size: 12px;
            color: #bfcbd2;
        }

        #airportInput {
            width: 100%;
            height: 34px;
            padding: 0 10px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #f0f0f0;
            background: #172936;
            border: 1px solid #536b7a;
            border-radius: 4px;
            outline: none;
        }

        #airportInput::placeholder {
            color: #718692;
            font-weight: normal;
            letter-spacing: 0;
        }

        #airportInput:focus {
            border-color: #0878b9;
            background: #233d4e;
        }

        #chartSelect {
            width: 100%;
            height: 34px;
            margin-top: 8px;
            padding: 0 8px;
            font-size: 13px;
            color: #f0f0f0;
            background: #172936;
            border: 1px solid #536b7a;
            border-radius: 4px;
            outline: none;
        }

        #chartSelect:focus {
            border-color: #0878b9;
        }

        #chartSelect:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #openChartButton {
            margin-top: 8px;
        }

        #openChartButton:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        #pdfInput {
            width: 100%;

            padding: 8px;

            font-size: 13px;

            color: #f0f0f0;

            background: #172936;

            border: 1px solid #536b7a;

            border-radius: 4px;
        }


        /* =====================================
           УПРАВЛЕНИЕ
           ===================================== */

        #pageBlock {
            display: none;

            height: 100%;

            min-height: 0;

            flex-direction: column;

            background: #203748;
        }


        /* =====================================
           ВЕРХНИЕ КНОПКИ
           ===================================== */

        .top-controls {
            padding: 10px;

            flex-shrink: 0;

            border-bottom: 1px solid #536b7a;

            background: #172936;
        }


        .top-button {
            width: 100%;

            padding: 8px;

            margin: 3px 0;

            font-size: 13px;

            color: #e5edf1;

            background: #29485d;

            border: 1px solid #536b7a;

            border-radius: 5px;

            cursor: pointer;

            transition:
                background 0.15s ease,
                border-color 0.15s ease;
        }


        .top-button:hover {
            background: #0878b9;

            border-color: #0983c9;
        }


        .top-button:active {
            background: #065d8d;
        }


        /* =====================================
           КНОПКИ ПОВОРОТА
           ===================================== */

        .rotate-controls {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 4px;
            margin: 3px 0;
        }


        .rotate-button {
            width: 34px;
            height: 30px;
            padding: 0;
            border: 1px solid #536b7a;
            border-radius: 4px;
            color: #e5edf1;
            background: #29485d;
            font-size: 20px;
            line-height: 28px;
            font-family: Arial, sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition:
                background 0.15s ease,
                border-color 0.15s ease,
                transform 0.08s ease;
        }


        .rotate-button:hover {
            background: #0878b9;
            border-color: #0983c9;
        }


        .rotate-button:active {
            background: #065d8d;
            transform: scale(0.94);
        }


        /* =====================================
           ФИЛЬТРЫ
           ===================================== */

        .chart-filter {
            padding: 7px 10px 9px 10px;

            background: #172936;

            border-bottom: 1px solid #536b7a;

            flex-shrink: 0;
        }


        /* =====================================
           ПОИСК
           ===================================== */

        .search-wrapper {
            position: relative;

            width: 100%;
        }


        .search-icon {
            position: absolute;

            left: 9px;
            top: 50%;

            transform: translateY(-50%);

            color: #7f939f;

            font-size: 14px;

            pointer-events: none;
        }


        #chartSearch {
            width: 100%;

            height: 31px;

            padding: 0 9px 0 30px;

            border: 1px solid #233f52;

            border-radius: 4px;

            outline: none;

            color: #dce6eb;

            background: #203748;

            font-size: 12px;

            transition:
                border-color 0.15s ease,
                background 0.15s ease;
        }


        #chartSearch::placeholder {
            color: #718692;
        }


        #chartSearch:focus {
            border-color: #0878b9;

            background: #233d4e;
        }


        /* =====================================
           КНОПКИ КАТЕГОРИЙ
           ===================================== */

        .category-buttons {
            display: flex;

            align-items: center;

            gap: 4px;

            margin-top: 7px;
        }


        .category-button {
            flex: 1;

            min-width: 0;

            height: 20px;

            padding: 0 4px;

            border: 0;

            border-radius: 3px;

            background: transparent;

            font-size: 10px;

            font-weight: bold;

            cursor: pointer;

            transition:
                background 0.15s ease,
                color 0.15s ease,
                opacity 0.15s ease;
        }


        /*
         * Цвета категорий.
         */

        .category-button[data-category="ALL"] {
            color: #aab9c1;
        }


        .category-button[data-category="STAR"] {
            color: #69c86b;
        }


        .category-button[data-category="APP"] {
            color: #e3a65a;
        }


        .category-button[data-category="TAXI"] {
            color: #18b9e8;
        }


        .category-button[data-category="SID"] {
            color: #e06ca7;
        }


        .category-button[data-category="REF"] {
            color: #ad75db;
        }


        .category-button:hover {
            background: rgba(255,255,255,0.07);
        }


        /*
         * Активная кнопка.
         */

        .category-button.active {
            color: #ffffff !important;

            background: #08a8d5;

            box-shadow:
                0 0 0 1px rgba(255,255,255,0.08);
        }


        .category-button.active:hover {
            background: #09b5e4;
        }


        /* =====================================
           ЗАГОЛОВОК СПИСКА
           ===================================== */

        .pages-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 8px 12px;

            background: #172936;

            border-bottom: 1px solid #536b7a;

            flex-shrink: 0;
        }


        .pages-title {
            font-size: 13px;

            font-weight: bold;

            color: #f0f0f0;
        }


        .pages-hint {
            font-size: 10px;

            color: #9fb0bb;
        }


        /* =====================================
           СПИСОК СТРАНИЦ
           ===================================== */

        #pageList {
            flex: 1;

            min-height: 0;

            overflow-y: auto;

            overflow-x: hidden;

            background: #1e3344;
        }


        /* =====================================
           ПОЛОСА ПРОКРУТКИ
           ===================================== */

        #pageList::-webkit-scrollbar {
            width: 8px;
        }


        #pageList::-webkit-scrollbar-track {
            background: #172936;
        }


        #pageList::-webkit-scrollbar-thumb {
            background: #536b7a;

            border-radius: 4px;
        }


        #pageList::-webkit-scrollbar-thumb:hover {
            background: #6e8797;
        }


        /* =====================================
           ЭЛЕМЕНТ СТРАНИЦЫ
           ===================================== */

        .page-item {
            display: flex;

            align-items: stretch;

            min-height: 57px;

            border-bottom:
                1px solid rgba(255,255,255,0.06);

            background: #203748;

            cursor: pointer;

            transition:
                background 0.15s ease;
        }


        .page-item:hover {
            background: #29485d;
        }


        /* =====================================
           ТЕКУЩАЯ СТРАНИЦА
           ===================================== */

        .page-item.active {
            background: #0878b9;
        }


        .page-item.active:hover {
            background: #0983c9;
        }


        /* =====================================
           ЗАКРЕПЛЁННАЯ
           ===================================== */

        .page-item.pinned {
            background: #725f20;
        }


        .page-item.pinned:hover {
            background: #856e26;
        }


        .page-item.active.pinned {
            background: #0b78a8;
        }


        /* =====================================
           ТЕКСТ
           ===================================== */

        .page-info {
            flex: 1;

            min-width: 0;

            padding: 9px 8px 9px 14px;

            display: flex;

            align-items: center;
        }


        .page-name {
            color: #f0f0f0;

            font-size: 13px;

            line-height: 1.3;

            font-weight: 500;

            word-break: break-word;
        }


        .page-title {
            display: block;
        }


        .page-id {
            display: block;

            margin-top: 2px;

            font-weight: 700;

            color: #ffffff;
        }


        .page-number {
            color: #bfcbd2;

            font-size: 12px;

            font-weight: bold;
        }


        /* =====================================
           КАТЕГОРИЯ НА СТРАНИЦЕ
           ===================================== */

        .page-category {
            display: inline-block;

            margin-top: 5px;

            padding: 2px 5px;

            border-radius: 3px;

            font-size: 8px;

            font-weight: bold;

            letter-spacing: 0.4px;

            color: rgba(255,255,255,0.75);

            background: rgba(0,0,0,0.16);
        }


        /* =====================================
           PIN
           ===================================== */

        .pin-button {
            width: 48px;

            min-width: 48px;

            border: 0;

            border-left:
                1px solid rgba(255,255,255,0.06);

            background: transparent;

            color: #d7e0e5;

            font-size: 21px;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            transition:
                background 0.15s ease,
                color 0.15s ease;
        }


        .pin-button:hover {
            background:
                rgba(255,255,255,0.10);

            color: white;
        }


        .page-item.pinned .pin-button {
            color: #ffe27a;
        }


        .page-item.pinned .pin-button:hover {
            color: #fff0a8;
        }


        /* =====================================
           НЕТ РЕЗУЛЬТАТОВ
           ===================================== */

        .no-results {
            padding: 25px 15px;

            text-align: center;

            color: #8296a2;

            font-size: 12px;

            line-height: 1.5;
        }


        /* =====================================
           ОБЛАСТЬ PDF
           ===================================== */

        .viewer {
            position: relative;

            flex: 1;

            min-width: 0;
            min-height: 0;

            overflow: hidden;

            background: #a9a9a9;

            cursor: default;

            touch-action: none;
        }


        .viewer.dragging {
            cursor: grabbing;
        }


        /* =====================================
           CANVAS
           ===================================== */

        #pdfCanvas {
            position: absolute;

            left: 0;
            top: 0;

            display: block;

            background: white;

            box-shadow:
                0 3px 15px
                rgba(0, 0, 0, 0.35);

            user-select: none;

            -webkit-user-drag: none;

            will-change: transform;

            transform-origin: 0 0;

            transition:
                filter 0.15s ease;
        }


        /* =====================================
           УЗКИЙ ЭКРАН
           ===================================== */

        .mobile-toolbar,.mobile-backdrop{display:none}

        @media (max-width:700px){
            .app{position:relative;height:100dvh}
            .sidebar{
                position:fixed;z-index:1000;left:0;top:0;bottom:0;
                width:min(88vw,350px);min-width:0;height:100dvh;
                transform:translateX(-105%);transition:transform .22s ease;
                box-shadow:8px 0 25px rgba(0,0,0,.35)
            }
            .app.mobile-menu-open .sidebar{transform:translateX(0)}
            .viewer{width:100%;height:100dvh}
            .mobile-toolbar{
                position:fixed;z-index:900;left:10px;right:10px;top:10px;
                height:44px;display:flex;align-items:center;
                justify-content:space-between;gap:6px;pointer-events:none
            }
.mobile-toolbar button,.mobile-toolbar-title{
                pointer-events:auto;height:42px;min-width:42px;border:1px solid rgba(255,255,255,.25);
                border-radius:8px;background:rgba(23,41,54,.92);color:#fff;
                display:flex;align-items:center;justify-content:center;font-size:24px;
                touch-action:manipulation;-webkit-tap-highlight-color:transparent
            }
            .mobile-toolbar-title{padding:0 10px;font-size:13px;font-weight:bold}
            .mobile-backdrop{position:fixed;z-index:950;inset:0;background:rgba(0,0,0,.45);touch-action:none}
            .app.mobile-menu-open .mobile-backdrop{display:block}
            .top-button{min-height:44px}
            .rotate-button{width:44px;height:40px}
            #chartSearch{height:42px;font-size:14px}
            .category-button{min-height:34px}
            .page-item{min-height:64px}
            .pin-button{width:54px;min-width:54px}
            .airport-result{min-height:56px}
            .app.initial .sidebar{position:relative;width:100%;min-width:0;transform:none;box-shadow:none}
            .app.initial .mobile-toolbar,.app.initial .mobile-backdrop{display:none!important}
        }

    

        /* =====================================
           ПОИСК АЭРОПОРТА — ВАРИАНТЫ КАК В AIRPORT SELECTOR
           ===================================== */

        .airport-search-box {
            position: relative;
            width: 100%;
        }

        .airport-input-wrapper {
            position: relative;
            width: 100%;
        }

        .airport-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 22px;
            line-height: 1;
            color: #111;
            pointer-events: none;
            z-index: 2;
        }

        #airportInput {
            width: 100%;
            height: 49px;
            padding: 0 14px 0 42px;
            border: 1px solid #b7b7b7;
            border-radius: 0;
            outline: none;
            color: #171717;
            background: #ffffff;
            font-size: 20px;
            font-family: Arial, sans-serif;
        }

        #airportInput::placeholder {
            color: #777;
        }

        #airportInput:focus {
            border-color: #888;
        }

        #airportSuggestions {
            width: 100%;
            margin-top: 20px;
            display: none;
        }

        .airport-result {
            width: 100%;
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 8px 14px;
            background: #ffffff;
            color: #111;
            cursor: pointer;
        }

        .airport-result:hover {
            background: #f5f5f5;
        }

        .airport-result-icon {
            width: 27px;
            min-width: 27px;
            height: 27px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #075b9d;
            font-size: 25px;
        }

        .airport-result-main {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: baseline;
            gap: 10px;
            overflow: hidden;
        }

        .airport-result-code {
            font-size: 19px;
            font-weight: 700;
            white-space: nowrap;
        }

        .airport-result-name {
            font-size: 18px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .airport-enter-button {
            flex-shrink: 0;
            min-width: 66px;
            height: 32px;
            padding: 0 11px;
            border: 1px solid #777;
            border-radius: 2px;
            background: #eeeeee;
            color: #111;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .airport-enter-button:hover {
            background: #e2e2e2;
        }

        .airport-no-results {
            padding: 12px 14px;
            background: #ffffff;
            color: #666;
            font-size: 14px;
        }

        /* Старый select больше не показываем — выбор выполняется
           непосредственно нажатием на найденный вариант. */
        #chartSelect,
        #openChartButton {
            display: none;
        }

</style>

</head>


<body>


<div class="app initial" id="app">


    <!-- =====================================
         БОКОВАЯ ПАНЕЛЬ
         ===================================== -->

    <aside class="sidebar">


        <div class="sidebar-header">

            <h1>
                SkyChartsViewer
            </h1>

        </div>


        <!-- =================================
             ВЫБОР PDF
             ================================= -->

        <div id="uploadBlock">

            <div class="airport-label">
                Код аэропорта
            </div>

            <div class="airport-search-box">

                <div class="airport-input-wrapper">
                    <span class="airport-search-icon">⌕</span>
                    <input
                        type="text"
                        id="airportInput"
                        placeholder="Введите код аэропорта"
                        autocomplete="off"
                        spellcheck="false"
                        maxlength="4"
                    >
                </div>

                <div id="airportSuggestions"></div>

            </div>

        </div>


        <!-- =====================================
             УПРАВЛЕНИЕ
             ===================================== -->

        <div id="pageBlock">


            <!-- =================================
                 ВЕРХНИЕ КНОПКИ
                 ================================= -->

            <div class="top-controls">

                <button
                    class="top-button"
                    id="changeFile"
                >
                    📄 Выбрать другой чарт
                </button>


                <div class="rotate-controls">
                    <button
                        class="rotate-button"
                        id="rotateLeftButton"
                        type="button"
                        title="Повернуть против часовой стрелки"
                        aria-label="Повернуть против часовой стрелки"
                    >↶</button>

                    <button
                        class="rotate-button"
                        id="rotateRightButton"
                        type="button"
                        title="Повернуть по часовой стрелке"
                        aria-label="Повернуть по часовой стрелке"
                    >↷</button>
                </div>


                <button
                    class="top-button"
                    id="invertButton"
                >
                    ☾ Ночная версия
                </button>

            </div>


            <!-- =================================
                 ПОИСК И КАТЕГОРИИ
                 ================================= -->

            <div class="chart-filter">


                <div class="search-wrapper">

                    <span class="search-icon">
                        🔍
                    </span>

                    <input
                        type="text"
                        id="chartSearch"
                        placeholder="Filter charts ..."
                        autocomplete="off"
                    >

                </div>


                <div class="category-buttons">


                    <button
                        class="category-button"
                        data-category="STAR"
                    >
                        STAR
                    </button>


                    <button
                        class="category-button"
                        data-category="APP"
                    >
                        APP
                    </button>


                    <button
                        class="category-button active"
                        data-category="TAXI"
                    >
                        TAXI
                    </button>


                    <button
                        class="category-button"
                        data-category="SID"
                    >
                        SID
                    </button>


                    <button
                        class="category-button"
                        data-category="REF"
                    >
                        REF
                    </button>


                </div>

            </div>


            <!-- =================================
                 ЗАГОЛОВОК СПИСКА
                 ================================= -->

            <div class="pages-header">

                <span
                    class="pages-title"
                    id="pagesTitle"
                >
                    TAXI
                </span>

                <span class="pages-hint">
                    📌 — закрепить
                </span>

            </div>


            <!-- =================================
                 СПИСОК СТРАНИЦ
                 ================================= -->

            <div id="pageList"></div>


        </div>


    </aside>


    <!-- =====================================
         ПРОСМОТР PDF
         ===================================== -->

    <main
        class="viewer"
        id="viewer"
    >

        <div class="mobile-toolbar" id="mobileToolbar">
            <button type="button" id="mobileMenuButton" aria-label="Меню">☰</button>
            <span class="mobile-toolbar-title">Просмотр</span>
        </div>
        <div class="mobile-backdrop" id="mobileBackdrop"></div>
        <canvas id="pdfCanvas"></canvas>

    </main>


</div>



<script>


    /* =====================================
       PDF.JS WORKER
       ===================================== */

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";



    /* =====================================
       ЭЛЕМЕНТЫ
       ===================================== */

    const airportInput =
        document.getElementById("airportInput");

    // Служебные элементы больше не нужны в интерфейсе, но старый код
    // использует их. Создаём их программно, чтобы не было null.
    let chartSelect =
        document.getElementById("chartSelect");

    if (!chartSelect) {
        chartSelect = document.createElement("select");
        chartSelect.id = "chartSelect";
        chartSelect.style.display = "none";
        document.body.appendChild(chartSelect);
    }

    let openChartButton =
        document.getElementById("openChartButton");

    if (!openChartButton) {
        openChartButton = document.createElement("button");
        openChartButton.id = "openChartButton";
        openChartButton.style.display = "none";
        document.body.appendChild(openChartButton);
    }

    let chartInfo =
        document.getElementById("chartInfo");

    if (!chartInfo) {
        chartInfo = document.createElement("div");
        chartInfo.id = "chartInfo";
        chartInfo.style.display = "none";
        document.body.appendChild(chartInfo);
    }


    const uploadBlock =
        document.getElementById("uploadBlock");


    const pageBlock =
        document.getElementById("pageBlock");


    const pageList =
        document.getElementById("pageList");


    const changeFile =
        document.getElementById("changeFile");


    const rotateLeftButton =
        document.getElementById("rotateLeftButton");


    const rotateRightButton =
        document.getElementById("rotateRightButton");


    const invertButton =
        document.getElementById("invertButton");


    const viewer =
        document.getElementById("viewer");


    const canvas =
        document.getElementById("pdfCanvas");


    const ctx =
        canvas.getContext("2d");

    const mobileMenuButton=document.getElementById("mobileMenuButton");

    const mobileBackdrop=document.getElementById("mobileBackdrop");


    const chartSearch =
        document.getElementById("chartSearch");


    const pagesTitle =
        document.getElementById("pagesTitle");


    const categoryButtons =
        document.querySelectorAll(
            ".category-button"
        );



    /* =====================================
       ПЕРЕМЕННЫЕ
       ===================================== */

    let pdfDocument = null;


    /*
     * Названия страниц.
     *
     * Формат:
     *
     * {
     *     1: "AIRPORT BRIEFING (GEN) 10-1P"
     * }
     */

    let pageNames = {};


    /*
     * Категории страниц.
     *
     * Формат:
     *
     * {
     *     1: "REF",
     *     2: "REF",
     *     6: "STAR"
     * }
     */

    let pageCategories = {};


    /*
     * Закреплённые страницы.
     */

    let pinnedPages = new Set();


    /*
     * Имя PDF.
     */

    let currentPdfName = "";


    /*
     * Текущая страница.
     */

    let currentPage = 1;


    /*
     * Текущая категория.
     *
     * По умолчанию TAXI,
     * как на показанном интерфейсе.
     */

    let currentCategory = "TAXI";


    /*
     * Поиск.
     */

    let currentSearch = "";

    /*
     * Список PDF-файлов, полученный с сервера.
     *
     * Формат:
     *
     * [
     *     {
     *         name: "UUEE.pdf",
     *         code: "UUEE",
     *         hasAsoc: true
     *     }
     * ]
     */
    // Список PDF формируется PHP напрямую из папки /charts/.
    // Никаких JSON-файлов и directory listing браузера не требуется.
    const SERVER_CHARTS = <?php echo json_encode($serverCharts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    let availableCharts = SERVER_CHARTS.map(function (chart) {
        return Object.assign({}, chart);
    });

    let chartFiles = new Map();

    availableCharts.forEach(function (chart) {
        chartFiles.set(chart.name, chart);
    });

    /*
     * Пути к каталогам на сервере.
     * index.html находится рядом с папками charts и asoc.
     */
    const CHARTS_PATH = "./charts/";
    const ASOC_PATH = "./asoc/";

    let chartListRequestId = 0;


    /*
     * Поворот.
     */

    let rotation = 0;


    /*
     * Автоматический поворот по ориентации текста.
     *
     * Для каждой страницы запоминается найденный
     * угол текста. Если основная масса текста
     * расположена вертикально, страница автоматически
     * поворачивается так, чтобы текст стал горизонтальным.
     */
    let automaticRotation = 0;

    /*
     * Ручная поправка кнопками поворота.
     * После автоматического определения пользователь
     * может дополнительно повернуть страницу.
     */
    let manualRotation = 0;


    /*
     * Инверсия.
     */

    let inverted = false;


    /*
     * Масштаб.
     */

    let zoom = 1;

    let renderedZoom = 1;


    const MIN_ZOOM = 0.25;

    const MAX_ZOOM = 5;


    /*
     * Положение страницы.
     */

    let offsetX = 0;

    let offsetY = 0;


    /*
     * Целевые значения.
     */

    let targetZoom = 1;

    let targetOffsetX = 0;

    let targetOffsetY = 0;


    /*
     * Анимация.
     */

    let animationFrame = null;


    /*
     * Таймер рендера.
     */

    let renderTimer = null;


    /*
     * Перемещение мышью.
     */

    let isDragging = false;

    let dragStartX = 0;

    let dragStartY = 0;

    let dragOffsetX = 0;

    let dragOffsetY = 0;



    /* =====================================
       ПОЛУЧЕНИЕ ИМЕНИ TXT
       ===================================== */

    function getAssociationFileName(
        pdfFileName
    ) {

        return pdfFileName.replace(
            /\.pdf$/i,
            ".txt"
        );

    }



    /* =====================================
       ЗАГРУЗКА TXT ИЗ ASOC
       ===================================== */

    async function loadAssociationFile(pdfFileName) {
        pageNames = {};
        pageCategories = {};
        const chart = chartFiles.get(pdfFileName);
        const base = pdfFileName.replace(/\.pdf$/i, "");
        const urls = [];
        if (chart && chart.asocUrl) urls.push(chart.asocUrl);
        urls.push(
            ASOC_PATH + encodeURIComponent(base + ".txt"),
            ASOC_PATH + encodeURIComponent(base.toUpperCase() + ".txt"),
            ASOC_PATH + encodeURIComponent(base.toLowerCase() + ".txt")
        );
        for (const url of [...new Set(urls)]) {
            try {
                const r = await fetch(url, {cache:"no-store"});
                if (!r.ok) continue;
                const text = await r.text();
                if (/<html[\s>]/i.test(text) &&
                    !/^\s*(REF|STAR|SID|TAXI|APP)\s*$/im.test(text)) continue;
                const parsed = parseAssociationFile(text);
                pageNames = parsed.names;
                pageCategories = parsed.categories;
                console.log("ASOC:", url, Object.keys(pageNames).length);
                return;
            } catch(e) { console.warn("ASOC:", url, e); }
        }
        console.log("ASOC не найден:", pdfFileName);
    }
    /* =====================================
       РАЗБОР ASOC
       ===================================== */

    function parseAssociationFile(
        text
    ) {

        const names = {};

        const categories = {};


        /*
         * Разрешённые категории.
         */

        const allowedCategories = new Set([
            "REF",
            "STAR",
            "TAXI",
            "SID",
            "APP"
        ]);


        /*
         * Текущая категория.
         */

        let currentSection = null;


        const lines =
            text.split(/\r?\n/);


        for (
            const rawLine of lines
        ) {

            const line =
                rawLine.trim();


            if (!line) {

                continue;
            }


            /*
             * Проверяем заголовок:
             *
             * REF
             * STAR
             * TAXI
             * SID
             * APP
             */

            const sectionMatch =
                line.match(
                    /^([A-Z]+)\s*$/i
                );


            if (sectionMatch) {

                const section =
                    sectionMatch[1]
                        .toUpperCase();


                if (
                    allowedCategories.has(
                        section
                    )
                ) {

                    currentSection =
                        section;

                    continue;
                }

            }


            /*
             * Проверяем строку:
             *
             * 1 = "Название"
             */

            const pageMatch =
                line.match(
                    /^\s*(\d+)\s*=\s*["'](.*?)["']\s*$/
                );


            if (!pageMatch) {

                continue;
            }


            const pageNumber =
                Number(
                    pageMatch[1]
                );


            const pageName =
                pageMatch[2].trim();


            if (
                pageNumber >= 1 &&
                pageName.length > 0
            ) {

                names[pageNumber] =
                    pageName;


                /*
                 * Если перед страницей
                 * был заголовок категории,
                 * записываем категорию.
                 */

                if (currentSection) {

                    categories[pageNumber] =
                        currentSection;

                }

            }

        }


        return {
            names: names,
            categories: categories
        };

    }



    /* =====================================
       КЛЮЧ LOCALSTORAGE
       ===================================== */

    function getPinnedStorageKey() {

        return "pdfViewerPinned_" +
            currentPdfName;

    }



    /* =====================================
       ЗАГРУЗКА PIN
       ===================================== */

    function loadPinnedPages() {

        pinnedPages =
            new Set();


        if (!currentPdfName) {

            return;
        }


        try {

            const saved =
                localStorage.getItem(
                    getPinnedStorageKey()
                );


            if (!saved) {

                return;
            }


            const pages =
                JSON.parse(
                    saved
                );


            if (
                Array.isArray(pages)
            ) {

                for (
                    const page of pages
                ) {

                    const number =
                        Number(page);


                    if (
                        Number.isInteger(number) &&
                        number >= 1
                    ) {

                        pinnedPages.add(
                            number
                        );

                    }

                }

            }

        }

        catch (error) {

            console.warn(
                "Не удалось загрузить закреплённые страницы:",
                error
            );

        }

    }



    /* =====================================
       СОХРАНЕНИЕ PIN
       ===================================== */

    function savePinnedPages() {

        if (!currentPdfName) {

            return;
        }


        try {

            localStorage.setItem(

                getPinnedStorageKey(),

                JSON.stringify(
                    Array.from(
                        pinnedPages
                    )
                )

            );

        }

        catch (error) {

            console.warn(
                "Не удалось сохранить закреплённые страницы:",
                error
            );

        }

    }



    /* =====================================
       РАЗБОР НАЗВАНИЯ СТРАНИЦЫ
       ===================================== */

    function formatPageName(
        name
    ) {

        /*
         * ID может начинаться
         * с любого номера.
         *
         * Примеры:
         *
         * 10-1P
         * 10-1P1
         * 10-2
         * 11-1
         * 12-40
         * 16-1
         */

        const match =
            name.match(
                /^(.*?)(?:\s+)(\d+-\d+[A-Z0-9-]*)\s*$/i
            );


        if (match) {

            const title =
                match[1].trim();


            const pageId =
                match[2].trim();


            return {

                title: title,

                id: pageId

            };

        }


        return {

            title: name,

            id: ""

        };

    }



    /* =====================================
       ПРОВЕРКА ФИЛЬТРА
       ===================================== */

    function pageMatchesFilter(
        pageNumber
    ) {

        /*
         * Категория.
         */

        if (
            currentCategory !== "ALL"
        ) {

            const category =
                pageCategories[
                    pageNumber
                ];


            if (
                category !==
                currentCategory
            ) {

                return false;
            }

        }


        /*
         * Поиск.
         */

        if (
            currentSearch
        ) {

            const name =
                pageNames[
                    pageNumber
                ] || "";


            const formatted =
                formatPageName(
                    name
                );


            const searchText =
                (
                    name +
                    " " +
                    formatted.title +
                    " " +
                    formatted.id +
                    " " +
                    pageNumber
                ).toLowerCase();


            if (
                !searchText.includes(
                    currentSearch
                )
            ) {

                return false;
            }

        }


        return true;

    }



    /* =====================================
       ОБНОВЛЕНИЕ ЗАГОЛОВКА
       ===================================== */

    function updatePagesTitle() {

        if (
            currentSearch
        ) {

            pagesTitle.textContent =
                currentCategory === "ALL"
                    ? "Поиск"
                    : currentCategory + " · Поиск";

        }

        else {

            pagesTitle.textContent =
                currentCategory === "ALL"
                    ? "Все страницы"
                    : currentCategory;

        }

    }



    /* =====================================
       СОЗДАНИЕ СПИСКА
       ===================================== */

    function createPageList(
        pageCount
    ) {

        pageList.innerHTML = "";


        let visibleCount = 0;


        for (
            let i = 1;
            i <= pageCount;
            i++
        ) {

            /*
             * Проверяем фильтры.
             */

            if (
                !pageMatchesFilter(i)
            ) {

                continue;
            }


            visibleCount++;


            /*
             * Главный элемент.
             */

            const pageItem =
                document.createElement(
                    "div"
                );


            pageItem.className =
                "page-item";


            pageItem.dataset.page =
                i;


            /*
             * Закреплённая.
             */

            if (
                pinnedPages.has(i)
            ) {

                pageItem.classList.add(
                    "pinned"
                );

            }


            /*
             * Информация.
             */

            const pageInfo =
                document.createElement(
                    "div"
                );


            pageInfo.className =
                "page-info";


            /*
             * Название.
             */

            const pageName =
                document.createElement(
                    "div"
                );


            pageName.className =
                "page-name";


            if (
                pageNames[i] &&
                pageNames[i].trim() !== ""
            ) {

                const formatted =
                    formatPageName(
                        pageNames[i]
                    );


                /*
                 * Название.
                 */

                const title =
                    document.createElement(
                        "span"
                    );


                title.className =
                    "page-title";


                title.textContent =
                    formatted.title;


                pageName.appendChild(
                    title
                );


                /*
                 * ID.
                 */

                if (
                    formatted.id
                ) {

                    const id =
                        document.createElement(
                            "span"
                        );


                    id.className =
                        "page-id";


                    id.textContent =
                        formatted.id;


                    pageName.appendChild(
                        id
                    );

                }


                /*
                 * Категория.
                 */

                const category =
                    pageCategories[i];


                if (category) {

                    const categoryElement =
                        document.createElement(
                            "span"
                        );


                    categoryElement.className =
                        "page-category";


                    categoryElement.textContent =
                        category;


                    pageName.appendChild(
                        categoryElement
                    );

                }

            }

            else {

                /*
                 * Если названия нет,
                 * показываем номер PDF.
                 */

                pageName.innerHTML =
                    '<span class="page-number">' +
                    i +
                    '</span>';

            }


            pageInfo.appendChild(
                pageName
            );


            /*
             * Кнопка PIN.
             */

            const pinButton =
                document.createElement(
                    "button"
                );


            pinButton.className =
                "pin-button";


            pinButton.type =
                "button";


            pinButton.title =
                pinnedPages.has(i)
                    ? "Открепить страницу"
                    : "Закрепить страницу";


            pinButton.textContent =
                "📌";


            pinButton.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();


                    togglePinnedPage(
                        i,
                        pageItem,
                        pinButton
                    );

                }
            );


            /*
             * Открытие страницы.
             */

            pageItem.addEventListener(
                "click",
                function () {

                    selectPage(
                        i
                    );

                }
            );


            pageItem.appendChild(
                pageInfo
            );


            pageItem.appendChild(
                pinButton
            );


            pageList.appendChild(
                pageItem
            );

        }


        /*
         * Если ничего не найдено.
         */

        if (
            visibleCount === 0
        ) {

            const noResults =
                document.createElement(
                    "div"
                );


            noResults.className =
                "no-results";


            if (
                currentSearch
            ) {

                noResults.innerHTML =
                    "Ничего не найдено.<br><br>" +
                    "Попробуйте изменить запрос.";

            }

            else {

                noResults.textContent =
                    "В этой категории нет страниц.";

            }


            pageList.appendChild(
                noResults
            );

        }


        updatePagesTitle();


        updatePageListActiveState();

    }



    /* =====================================
       ПЕРЕКЛЮЧЕНИЕ КАТЕГОРИИ
       ===================================== */

    function setCategory(
        category
    ) {

        currentCategory =
            category;


        /*
         * Обновляем активную кнопку.
         */

        categoryButtons.forEach(
            function (button) {

                if (
                    button.dataset.category ===
                    category
                ) {

                    button.classList.add(
                        "active"
                    );

                }

                else {

                    button.classList.remove(
                        "active"
                    );

                }

            }
        );


        /*
         * Перерисовываем список.
         */

        if (
            pdfDocument
        ) {

            createPageList(
                pdfDocument.numPages
            );

        }

    }



    /* =====================================
       ПОИСК
       ===================================== */

    chartSearch.addEventListener(
        "input",
        function () {

            currentSearch =
                this.value
                    .trim()
                    .toLowerCase();


            if (
                pdfDocument
            ) {

                createPageList(
                    pdfDocument.numPages
                );

            }

        }
    );



    /* =====================================
       КНОПКИ КАТЕГОРИЙ
       ===================================== */

    categoryButtons.forEach(
        function (button) {

            button.addEventListener(
                "click",
                function () {

                    setCategory(
                        this.dataset.category
                    );

                }
            );

        }
    );



    /* =====================================
       ЗАКРЕПИТЬ / ОТКРЕПИТЬ
       ===================================== */

    function togglePinnedPage(
        pageNumber,
        pageItem,
        pinButton
    ) {

        if (
            pinnedPages.has(
                pageNumber
            )
        ) {

            pinnedPages.delete(
                pageNumber
            );


            pageItem.classList.remove(
                "pinned"
            );


            pinButton.title =
                "Закрепить страницу";

        }

        else {

            pinnedPages.add(
                pageNumber
            );


            pageItem.classList.add(
                "pinned"
            );


            pinButton.title =
                "Открепить страницу";

        }


        savePinnedPages();

    }



    /* =====================================
       АКТИВНАЯ СТРАНИЦА
       ===================================== */

    function updatePageListActiveState() {

        const items =
            pageList.querySelectorAll(
                ".page-item"
            );


        items.forEach(
            function (item) {

                const pageNumber =
                    Number(
                        item.dataset.page
                    );


                if (
                    pageNumber ===
                    currentPage
                ) {

                    item.classList.add(
                        "active"
                    );

                }

                else {

                    item.classList.remove(
                        "active"
                    );

                }

            }
        );

    }



    /* =====================================
       ПРОКРУТКА К ТЕКУЩЕЙ
       ===================================== */

    function scrollToCurrentPage() {

        const item =
            pageList.querySelector(
                '[data-page="' +
                currentPage +
                '"]'
            );


        if (!item) {

            return;
        }


        item.scrollIntoView({

            behavior: "smooth",

            block: "nearest"

        });

    }



    /* =====================================
       ВЫБОР СТРАНИЦЫ
       ===================================== */

    function selectPage(
        pageNumber
    ) {

        if (!pdfDocument) {

            return;
        }


        /*
         * При переходе на другую страницу
         * сбрасываем ручную поправку поворота.
         *
         * ВАЖНО:
         * сбрасываем её ДО изменения currentPage,
         * чтобы ручной поворот предыдущей страницы
         * никогда не переносился на новую.
         */
        if (currentPage !== pageNumber) {

            manualRotation = 0;

        }


        currentPage =
            pageNumber;


        updatePageListActiveState();


        showPage(
            pageNumber
        );

    }



    /* =====================================
       ОТКРЫТИЕ PDF
       ===================================== */

    async function openPDF(
        pdfFileName
    ) {

        try {

            currentPdfName =
                pdfFileName;


            /*
             * Загружаем ASOC.
             */

            await loadAssociationFile(
                pdfFileName
            );


            /*
             * Загружаем PIN.
             */

            loadPinnedPages();


            /*
             * Загружаем PDF напрямую с сервера.
             * index.html и папка charts находятся на одном сервере.
             */

            let chart = chartFiles.get(pdfFileName);

            /*
             * Если directory listing /charts/ ещё не успел загрузиться,
             * строим путь к PDF напрямую по имени файла.
             */
            if (!chart || !chart.pdfUrl) {
                const fallbackCode =
                    pdfFileName.replace(/\.pdf$/i, "").toUpperCase();

                if (!/^[A-Z0-9]{4}$/.test(fallbackCode)) {
                    throw new Error("Некорректный код аэропорта.");
                }

                chart = {
                    name: pdfFileName,
                    code: fallbackCode,
                    hasAsoc: false,
                    pdfUrl: CHARTS_PATH + encodeURIComponent(pdfFileName),
                    asocUrl: null
                };

                chartFiles.set(pdfFileName, chart);
            }

            const pdfResponse = await fetch(chart.pdfUrl, {
                cache: "no-store"
            });

            if (!pdfResponse.ok) {
                throw new Error(
                    "HTTP " + pdfResponse.status + " при загрузке " + pdfFileName
                );
            }

            const arrayBuffer = await pdfResponse.arrayBuffer();
            const typedArray = new Uint8Array(arrayBuffer);

            pdfDocument =
                await pdfjsLib
                    .getDocument(typedArray)
                    .promise;


            console.log(
                "PDF открыт.",
                pdfFileName,
                "Страниц:",
                pdfDocument.numPages
            );


            const app =
                document.getElementById("app");

            if (app) {
                app.classList.remove("initial");
                app.classList.remove("mobile-menu-open");
            }

            uploadBlock.style.display =
                "none";

            pageBlock.style.display =
                "flex";

            currentPage = 1;


            /*
             * По умолчанию TAXI.
             *
             * Если TAXI в ASOC отсутствует,
             * выбираем первую существующую категорию.
             */

            const availableCategories =
                Object.values(
                    pageCategories
                );


            if (
                !availableCategories.includes(
                    currentCategory
                )
            ) {

                const firstCategory =
                    [
                        "STAR",
                        "APP",
                        "TAXI",
                        "SID",
                        "REF"
                    ].find(
                        function (category) {
                            return availableCategories.includes(
                                category
                            );
                        }
                    );


                if (firstCategory) {
                    currentCategory =
                        firstCategory;
                }
                else {
                    currentCategory =
                        "ALL";
                }

            }


            /*
             * Поиск очищаем.
             */

            currentSearch = "";
            chartSearch.value = "";


            /*
             * Обновляем кнопку категории.
             */

            categoryButtons.forEach(
                function (button) {

                    button.classList.toggle(
                        "active",
                        button.dataset.category ===
                        currentCategory
                    );

                }
            );


            createPageList(
                pdfDocument.numPages
            );


            /*
             * Сбрасываем поворот.
             */

            rotation = 0;
            automaticRotation = 0;
            manualRotation = 0;


            /*
             * Сбрасываем ночной режим.
             */

            inverted = false;

            canvas.style.filter =
                "none";

            invertButton.textContent =
                "☾ Ночная версия";


            await showPage(
                1
            );

        }

        catch (error) {

            console.error(
                "Ошибка открытия PDF:",
                error
            );

            alert(
                "Не удалось открыть чарт " +
                pdfFileName +
                "."
            );

        }

    }



    /* =====================================
       ЗАГРУЗКА СПИСКА ЧАРТОВ С СЕРВЕРА
       ===================================== */

    async function loadCharts() {
        // Список уже получен PHP при загрузке страницы.
        // Браузер больше не делает fetch('./charts/'), поэтому 403 не возникает.
        availableCharts = SERVER_CHARTS.map(function (chart) {
            return Object.assign({}, chart);
        });

        chartFiles = new Map();
        availableCharts.forEach(function (chart) {
            chartFiles.set(chart.name, chart);
        });

        updateChartSelect();

        if (availableCharts.length) {
            chartInfo.innerHTML =
                'Список чартов загружен.<br><br>' +
                'Найдено PDF: <b>' + availableCharts.length + '</b>.';
        } else {
            chartInfo.innerHTML =
                '<b>В папке charts не найдено PDF-файлов.</b>';
        }
    }

    /* =====================================
       ОБНОВЛЕНИЕ ВЫПАДАЮЩЕГО СПИСКА
       ===================================== */


    /* =====================================
       ВАРИАНТЫ АЭРОПОРТОВ ПОД ПОЛЕМ ПОИСКА
       ===================================== */

    /*
     * Коды берутся только из найденных PDF — списка аэропортов здесь нет.
     */
    function getAirportCodes() {
        return [...new Set(availableCharts.map(c => c.code.toUpperCase()))];
    }

    const airportSuggestions = document.getElementById("airportSuggestions");

    async function checkAirportPDF(code) {
        const normalizedCode = code.trim().toUpperCase();
        if (!/^[A-Z0-9]{4}$/.test(normalizedCode)) return false;

        const fileName = normalizedCode + '.pdf';
        const url = CHARTS_PATH + encodeURIComponent(fileName);

        try {
            // GET используется вместо HEAD, поскольку некоторые серверы
            // запрещают HEAD, но разрешают обычную загрузку PDF.
            const response = await fetch(url, {
                method: 'GET',
                cache: 'no-store',
                headers: { 'Range': 'bytes=0-0' }
            });

            return response.ok || response.status === 206;
        } catch (error) {
            console.warn('Проверка аэропорта не удалась:', normalizedCode, error);
            return false;
        }
    }

    function renderAirportSuggestions() {
        const code = airportInput.value.trim().toUpperCase();
        airportSuggestions.innerHTML = "";

        if (!code) {
            airportSuggestions.style.display = "none";
            return;
        }

        const matches = getAirportCodes().filter(c => c.startsWith(code));

        // Если список уже содержит найденный аэропорт — показываем его.
        if (matches.length) {
            matches.slice(0, 20).forEach(c => {
                const x = document.createElement("div");
                x.className = "airport-result";
                const main = document.createElement("div");
                main.className = "airport-result-main";
                const codeEl = document.createElement("span");
                codeEl.className = "airport-result-code";
                codeEl.textContent = c;
                main.appendChild(codeEl);
                x.appendChild(main);
                x.onclick = async () => {
                    airportSuggestions.style.display = "none";
                    airportInput.value = c;
                    await openAirportByCode(c);
                };
                airportSuggestions.appendChild(x);
            });
            airportSuggestions.style.display = "block";
            return;
        }

        if (code.length < 4) {
            airportSuggestions.style.display = "block";
            const x = document.createElement("div");
            x.className = "airport-no-results";
            x.textContent = "Введите полный код ICAO";
            airportSuggestions.appendChild(x);
            return;
        }

        // При полном ICAO-коде проверяем реальный PDF напрямую.
        airportSuggestions.style.display = "block";
        const x = document.createElement("div");
        x.className = "airport-no-results";
        x.textContent = "Проверка " + code + "…";
        airportSuggestions.appendChild(x);

        checkAirportPDF(code).then(async exists => {
            if (airportInput.value.trim().toUpperCase() !== code) return;

            airportSuggestions.innerHTML = "";

            const result = document.createElement("div");
            result.className = exists ? "airport-result" : "airport-no-results";

            if (exists) {
                const main = document.createElement("div");
                main.className = "airport-result-main";
                const codeEl = document.createElement("span");
                codeEl.className = "airport-result-code";
                codeEl.textContent = code;
                main.appendChild(codeEl);
                result.appendChild(main);

                result.onclick = async () => {
                    airportSuggestions.style.display = "none";
                    await openAirportByCode(code);
                };

                airportSuggestions.appendChild(result);
                airportSuggestions.style.display = "block";

                const chart = {
                    name: code + '.pdf',
                    code: code,
                    hasAsoc: false,
                    pdfUrl: CHARTS_PATH + encodeURIComponent(code + '.pdf'),
                    asocUrl: null
                };
                availableCharts = [chart];
                chartFiles.set(chart.name, chart);
                updateChartSelect();
            } else {
                result.textContent = "Аэропорт " + code + " не найден";
                airportSuggestions.appendChild(result);
                airportSuggestions.style.display = "block";
                updateChartSelect();
            }
        });
    }

    async function openAirportByCode(code) {
        const normalizedCode = code.trim().toUpperCase();
        if (!/^[A-Z0-9]{4}$/.test(normalizedCode)) return;

        const name = normalizedCode + ".pdf";
        let chart = chartFiles.get(name);

        if (!chart) {
            chart = {
                name: name,
                code: normalizedCode,
                hasAsoc: false,
                pdfUrl: CHARTS_PATH + encodeURIComponent(name),
                asocUrl: null
            };
            chartFiles.set(name, chart);
        }

        await openPDF(chart.name);
    }

    function updateChartSelect() {

        const searchCode =
            airportInput.value
                .trim()
                .toUpperCase();


        chartSelect.innerHTML = "";


        if (!searchCode) {

            chartSelect.innerHTML =
                '<option value="">Введите код аэропорта</option>';

            chartSelect.disabled = true;
            openChartButton.disabled = true;

            return;
        }


        const matchingCharts =
            availableCharts.filter(
                function (chart) {

                    return chart.code
                        .toUpperCase()
                        .startsWith(searchCode);

                }
            );


        if (
            matchingCharts.length === 0
        ) {

            chartSelect.innerHTML =
                '<option value="">Чарты не найдены</option>';

            chartSelect.disabled = true;
            openChartButton.disabled = true;

            return;

        }


        matchingCharts.forEach(
            function (chart) {

                const option =
                    document.createElement(
                        "option"
                    );

                option.value =
                    chart.name;

                /*
                 * В списке показываем только XXXX,
                 * без .pdf.
                 *
                 * Если есть ASOC:
                 * XXXX ★
                 */

                option.textContent =
                    chart.code +
                    (
                        chart.hasAsoc
                            ? " ★"
                            : ""
                    );

                chartSelect.appendChild(
                    option
                );

            }
        );


        chartSelect.disabled = false;
        openChartButton.disabled = false;

        chartInfo.innerHTML =
            "Найдено чартов: <b>" +
            matchingCharts.length +
            "</b><br><br>" +
            "★ — имеется файл ассоциаций в папке <code>asoc</code>.";

    }


    /* =====================================
       ВВОД КОДА АЭРОПОРТА
       ===================================== */

    airportInput.addEventListener(
        "input",
        function () {

            this.value =
                this.value
                    .replace(
                        /[^a-zA-Z0-9]/g,
                        ""
                    )
                    .toUpperCase()
                    .slice(0, 4);

            updateChartSelect();
            renderAirportSuggestions();

        }
    );



    document.addEventListener("mousedown", function (event) {
        const box = document.querySelector(".airport-search-box");
        if (box && !box.contains(event.target)) {
            airportSuggestions.style.display = "none";
        }
    });

    /* =====================================
       ВЫБОР ЧАРТА
       ===================================== */

    chartSelect.addEventListener(
        "change",
        function () {

            openChartButton.disabled =
                !this.value;

        }
    );


    /* =====================================
       ОТКРЫТИЕ ЧАРТА
       ===================================== */

    openChartButton.addEventListener(
        "click",
        async function () {

            const selectedChart =
                chartSelect.value;

            if (!selectedChart) {
                return;
            }

            await openPDF(
                selectedChart
            );

        }
    );


    /*
     * Можно открыть чарт двойным кликом
     * по выпадающему списку.
     */

    chartSelect.addEventListener(
        "dblclick",
        async function () {

            if (!this.value) {
                return;
            }

            await openPDF(
                this.value
            );

        }
    );


    /*
     * Список чартов автоматически загружается с сервера
     * из папки /charts/.
     */



    /* =====================================
       АВТОМАТИЧЕСКАЯ ЗАГРУЗКА ЧАРТОВ
       ===================================== */

    loadCharts();


    /* =====================================
       РАСЧЁТ МАСШТАБА
       ===================================== */

    async function calculateFitZoom(
        pageNumber
    ) {

        const page =
            await pdfDocument.getPage(
                pageNumber
            );


        const viewport =
            page.getViewport({

                scale: 1,

                rotation:
                    rotation

            });


        const viewerWidth =
            viewer.clientWidth;


        const viewerHeight =
            viewer.clientHeight;


        const padding = 40;


        const scaleX =
            (
                viewerWidth -
                padding
            ) /
            viewport.width;


        const scaleY =
            (
                viewerHeight -
                padding
            ) /
            viewport.height;


        let fitZoom =
            Math.min(
                scaleX,
                scaleY
            );


        fitZoom =
            Math.max(

                MIN_ZOOM,

                Math.min(
                    MAX_ZOOM,
                    fitZoom
                )

            );


        return fitZoom;

    }



    /* =====================================
       АВТОМАТИЧЕСКОЕ ОПРЕДЕЛЕНИЕ ПОВОРОТА
       ПО ТЕКСТУ
       ===================================== */

    async function detectTextRotation(pageNumber) {

        if (!pdfDocument) {
            return 0;
        }

        try {

            const page =
                await pdfDocument.getPage(
                    pageNumber
                );

            const textContent =
                await page.getTextContent();


            /*
             * Вес текста считаем по количеству
             * символов. Поэтому длинная строка
             * влияет сильнее, чем короткая.
             */

            const orientationWeight = {
                0: 0,
                90: 0,
                180: 0,
                270: 0
            };


            let totalWeight = 0;


            for (
                const item of textContent.items
            ) {

                if (
                    !item ||
                    !item.str ||
                    !item.str.trim() ||
                    !item.transform ||
                    item.transform.length < 4
                ) {
                    continue;
                }


                const text =
                    item.str.trim();


                /*
                 * PDF.js transform:
                 *
                 * [a, b, c, d, e, f]
                 *
                 * Угол направления текста
                 * определяется по a/b.
                 */

                let angle =
                    Math.atan2(
                        item.transform[1],
                        item.transform[0]
                    ) *
                    180 /
                    Math.PI;


                if (angle < 0) {
                    angle += 360;
                }


                /*
                 * Приводим угол к ближайшему
                 * из 0 / 90 / 180 / 270.
                 */

                const normalizedAngle =
                    (
                        Math.round(
                            angle / 90
                        ) * 90
                    ) % 360;


                let orientation =
                    normalizedAngle;


                /*
                 * Вес = количество символов.
                 * Минимум 1, чтобы короткие элементы
                 * тоже учитывались.
                 */

                const weight =
                    Math.max(
                        1,
                        text.length
                    );


                if (
                    orientation === 0
                ) {
                    orientationWeight[0] += weight;
                }

                else if (
                    orientation === 90
                ) {
                    orientationWeight[90] += weight;
                }

                else if (
                    orientation === 180
                ) {
                    orientationWeight[180] += weight;
                }

                else if (
                    orientation === 270
                ) {
                    orientationWeight[270] += weight;
                }


                totalWeight += weight;

            }


            if (
                totalWeight === 0
            ) {

                console.log(
                    "Автоповорот: текст не найден."
                );

                return 0;
            }


            /*
             * Находим преобладающую ориентацию.
             */

            let dominantAngle = 0;
            let dominantWeight = 0;


            for (
                const angle of [0, 90, 180, 270]
            ) {

                if (
                    orientationWeight[angle] >
                    dominantWeight
                ) {

                    dominantWeight =
                        orientationWeight[angle];

                    dominantAngle =
                        angle;

                }

            }


            const dominantPercent =
                (
                    dominantWeight /
                    totalWeight
                ) * 100;


            console.log(
                "Ориентация текста:",
                {
                    horizontal:
                        (
                            orientationWeight[0] /
                            totalWeight *
                            100
                        ).toFixed(1) + "%",

                    right90:
                        (
                            orientationWeight[90] /
                            totalWeight *
                            100
                        ).toFixed(1) + "%",

                    upsideDown:
                        (
                            orientationWeight[180] /
                            totalWeight *
                            100
                        ).toFixed(1) + "%",

                    left90:
                        (
                            orientationWeight[270] /
                            totalWeight *
                            100
                        ).toFixed(1) + "%",

                    dominantAngle:
                        dominantAngle,

                    dominantPercent:
                        dominantPercent.toFixed(1) + "%"
                }
            );


            /*
             * Чтобы случайный вертикальный текст
             * (например, подписи сбоку) не заставлял
             * поворачивать всю карту, используем
             * порог 60%.
             */

            const MIN_TEXT_ROTATION_PERCENT = 60;


            if (
                dominantAngle === 90 &&
                dominantPercent >=
                    MIN_TEXT_ROTATION_PERCENT
            ) {

                /*
                 * Текст повернут на +90°.
                 * Чтобы сделать его горизонтальным,
                 * страницу поворачиваем на -90° = 270°.
                 */

                return 90;
            }


            if (
                dominantAngle === 270 &&
                dominantPercent >=
                    MIN_TEXT_ROTATION_PERCENT
            ) {

                /*
                 * Текст повернут на -90°.
                 * Страницу поворачиваем на +90°.
                 */

                return 270;
            }


            if (
                dominantAngle === 180 &&
                dominantPercent >=
                    MIN_TEXT_ROTATION_PERCENT
            ) {

                /*
                 * Если подавляющая часть текста имеет направление 180°,
                 * страница физически перевёрнута вверх ногами.
                 *
                 * Поэтому разворачиваем её ещё на 180°.
                 */
                return 180;
            }


            return 0;

        }

        catch (error) {

            console.warn(
                "Не удалось определить ориентацию текста:",
                error
            );

            return 0;

        }

    }



    /* =====================================
       ПОКАЗ СТРАНИЦЫ
       ===================================== */

    async function showPage(
        pageNumber
    ) {

        if (!pdfDocument) {

            return;
        }


        /*
         * Переход на другую страницу и сброс ручного
         * поворота обрабатываются в selectPage().
         *
         * Здесь manualRotation НЕ сбрасываем, потому что
         * showPage() вызывается также после нажатия кнопок
         * поворота и при изменении размера окна.
         */

        currentPage =
            pageNumber;


        updatePageListActiveState();


        /*
         * Если это обычное открытие страницы,
         * определяем ориентацию текста автоматически.
         *
         * manualRotation сохраняет поправку
         * только для текущего чарта.
         */
        automaticRotation =
            await detectTextRotation(
                pageNumber
            );


        rotation =
            (
                automaticRotation +
                manualRotation
            ) % 360;


        if (rotation < 0) {
            rotation += 360;
        }


        if (animationFrame) {

            cancelAnimationFrame(
                animationFrame
            );

            animationFrame = null;

        }


        zoom =
            await calculateFitZoom(
                pageNumber
            );


        renderedZoom =
            zoom;


        targetZoom =
            zoom;


        const page =
            await pdfDocument.getPage(
                pageNumber
            );


        const viewport =
            page.getViewport({

                scale:
                    renderedZoom,

                rotation:
                    rotation

            });


        const devicePixelRatio =
            Math.min(
                window.devicePixelRatio || 1,
                2.5
            );

        canvas.width =
            Math.ceil(viewport.width * devicePixelRatio);

        canvas.height =
            Math.ceil(viewport.height * devicePixelRatio);

        canvas.style.width =
            viewport.width + "px";

        canvas.style.height =
            viewport.height + "px";

        ctx.setTransform(
            devicePixelRatio,
            0,
            0,
            devicePixelRatio,
            0,
            0
        );


        /* Центрируем по CSS-размеру canvas, а не по физическому
           canvas.width/canvas.height, которые увеличены через DPR. */
        offsetX =
            (
                viewer.clientWidth -
                viewport.width
            ) / 2;


        offsetY =
            (
                viewer.clientHeight -
                viewport.height
            ) / 2;


        targetOffsetX =
            offsetX;


        targetOffsetY =
            offsetY;


        updateCanvas();


        await page.render({

            canvasContext:
                ctx,

            viewport:
                viewport

        }).promise;


        scrollToCurrentPage();

    }



    /* =====================================
       CANVAS
       ===================================== */

    function updateCanvas() {

        const visualScale =
            zoom /
            renderedZoom;


        canvas.style.transform =
            "translate3d(" +

            offsetX +

            "px, " +

            offsetY +

            "px, 0) " +

            "scale(" +

            visualScale +

            ")";

    }



    /* =====================================
       ПЛАВНАЯ АНИМАЦИЯ
       ===================================== */

    function startAnimation() {

        if (animationFrame) {

            return;
        }


        function animate() {

            const smooth =
                0.13;


            const zoomDifference =
                targetZoom -
                zoom;


            const xDifference =
                targetOffsetX -
                offsetX;


            const yDifference =
                targetOffsetY -
                offsetY;


            zoom +=
                zoomDifference *
                smooth;


            offsetX +=
                xDifference *
                smooth;


            offsetY +=
                yDifference *
                smooth;


            updateCanvas();


            if (

                Math.abs(
                    zoomDifference
                ) > 0.0005 ||

                Math.abs(
                    xDifference
                ) > 0.1 ||

                Math.abs(
                    yDifference
                ) > 0.1

            ) {

                animationFrame =
                    requestAnimationFrame(
                        animate
                    );

            }

            else {

                zoom =
                    targetZoom;


                offsetX =
                    targetOffsetX;


                offsetY =
                    targetOffsetY;


                updateCanvas();


                animationFrame =
                    null;

            }

        }


        animationFrame =
            requestAnimationFrame(
                animate
            );

    }



    /* =====================================
       ZOOM КОЛЕСОМ
       ===================================== */

    viewer.addEventListener(
        "wheel",
        function (event) {

            event.preventDefault();


            if (!pdfDocument) {

                return;
            }


            const rect =
                viewer.getBoundingClientRect();


            const mouseX =
                event.clientX -
                rect.left;


            const mouseY =
                event.clientY -
                rect.top;


            const pdfX =
                (
                    mouseX -
                    targetOffsetX
                ) /
                targetZoom;


            const pdfY =
                (
                    mouseY -
                    targetOffsetY
                ) /
                targetZoom;


            const zoomFactor =
                Math.exp(
                    -event.deltaY *
                    0.0008
                );


            let newZoom =
                targetZoom *
                zoomFactor;


            newZoom =
                Math.max(

                    MIN_ZOOM,

                    Math.min(
                        MAX_ZOOM,
                        newZoom
                    )

                );


            if (
                newZoom ===
                targetZoom
            ) {

                return;
            }


            targetZoom =
                newZoom;


            targetOffsetX =
                mouseX -
                pdfX *
                targetZoom;


            targetOffsetY =
                mouseY -
                pdfY *
                targetZoom;


            startAnimation();


            clearTimeout(
                renderTimer
            );


            renderTimer =
                setTimeout(
                    renderHighQualityPage,
                    150
                );

        },
        {
            passive: false
        }
    );



    /* =====================================
       ВЫСОКОКАЧЕСТВЕННЫЙ РЕНДЕР
       ===================================== */

    async function renderHighQualityPage() {

        if (!pdfDocument) {

            return;
        }


        const pageNumber =
            currentPage;


        const newZoom =
            targetZoom;


        const newOffsetX =
            targetOffsetX;


        const newOffsetY =
            targetOffsetY;


        const page =
            await pdfDocument.getPage(
                pageNumber
            );


        const viewport =
            page.getViewport({

                scale:
                    newZoom,

                rotation:
                    rotation

            });


        const devicePixelRatio =
            Math.min(
                window.devicePixelRatio || 1,
                2.5
            );

        canvas.width =
            Math.ceil(viewport.width * devicePixelRatio);

        canvas.height =
            Math.ceil(viewport.height * devicePixelRatio);

        canvas.style.width =
            viewport.width + "px";

        canvas.style.height =
            viewport.height + "px";

        ctx.setTransform(
            devicePixelRatio,
            0,
            0,
            devicePixelRatio,
            0,
            0
        );


        renderedZoom =
            newZoom;


        zoom =
            newZoom;


        offsetX =
            newOffsetX;


        offsetY =
            newOffsetY;


        targetZoom =
            newZoom;


        targetOffsetX =
            newOffsetX;


        targetOffsetY =
            newOffsetY;


        updateCanvas();


        await page.render({

            canvasContext:
                ctx,

            viewport:
                viewport

        }).promise;

    }



    /* =====================================
       ПОВОРОТ
       ===================================== */

    rotateLeftButton.addEventListener(
        "click",
        function () {

            if (!pdfDocument) {

                return;
            }


            manualRotation -= 90;


            if (
                manualRotation < 0
            ) {

                manualRotation = 270;
            }


            rotation =
                (
                    automaticRotation +
                    manualRotation
                ) % 360;


            showPage(
                currentPage
            );

        }
    );


    rotateRightButton.addEventListener(
        "click",
        function () {

            if (!pdfDocument) {

                return;
            }


            manualRotation += 90;


            if (
                manualRotation >= 360
            ) {

                manualRotation = 0;
            }


            rotation =
                (
                    automaticRotation +
                    manualRotation
                ) % 360;


            showPage(
                currentPage
            );

        }
    );



    /* =====================================
       НОЧНОЙ РЕЖИМ
       ===================================== */

    invertButton.addEventListener(
        "click",
        function () {

            inverted =
                !inverted;


            if (inverted) {

                canvas.style.filter =
                    "invert(1)";


                invertButton.textContent =
                    "☀ Обычная версия";

            }

            else {

                canvas.style.filter =
                    "none";


                invertButton.textContent =
                    "☾ Ночная версия";

            }

        }
    );



    /* =====================================
       ПЕРЕМЕЩЕНИЕ ЛКМ
       ===================================== */

    viewer.addEventListener(
        "mousedown",
        function (event) {

            if (
                event.button !== 0
            ) {

                return;
            }


            if (!pdfDocument) {

                return;
            }


            isDragging =
                true;


            dragStartX =
                event.clientX;


            dragStartY =
                event.clientY;


            dragOffsetX =
                targetOffsetX;


            dragOffsetY =
                targetOffsetY;


            viewer.classList.add(
                "dragging"
            );


            event.preventDefault();

        }
    );



    /* =====================================
       ПЕРЕМЕЩЕНИЕ
       ===================================== */

    window.addEventListener(
        "mousemove",
        function (event) {

            if (!isDragging) {

                return;
            }


            const dx =
                event.clientX -
                dragStartX;


            const dy =
                event.clientY -
                dragStartY;


            targetOffsetX =
                dragOffsetX +
                dx;


            targetOffsetY =
                dragOffsetY +
                dy;


            startAnimation();

        }
    );



    /* =====================================
       ОТПУСКАНИЕ ЛКМ
       ===================================== */

    window.addEventListener(
        "mouseup",
        function () {

            isDragging =
                false;


            viewer.classList.remove(
                "dragging"
            );

        }
    );



    window.addEventListener(
        "blur",
        function () {

            isDragging =
                false;


            viewer.classList.remove(
                "dragging"
            );

        }
    );




    /* =====================================
       СЕНСОРНОЕ УПРАВЛЕНИЕ
       ===================================== */

    mobileMenuButton?.addEventListener("click",()=>{
        document.getElementById("app")?.classList.toggle("mobile-menu-open");
    });
    mobileBackdrop?.addEventListener("click",()=>{
        document.getElementById("app")?.classList.remove("mobile-menu-open");
    });

    /*
       На мобильных:
       - 1 палец = перемещение страницы;
       - 2 пальца = масштабирование + перемещение;
       - никаких свайпов/перелистывания страниц.
    */
    let touchMode = "none";
    let touchStartX = 0;
    let touchStartY = 0;
    let touchLastX = 0;
    let touchLastY = 0;
    let touchStartZoom = 1;
    let touchStartOffsetX = 0;
    let touchStartOffsetY = 0;
    let touchPinchDistance = 1;
    let touchPinchCenterPdfX = 0;
    let touchPinchCenterPdfY = 0;

    function touchDistance(a, b) {
        return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
    }

    function touchCenter(a, b, rect) {
        return {
            x: (a.clientX + b.clientX) / 2 - rect.left,
            y: (a.clientY + b.clientY) / 2 - rect.top
        };
    }

    function startOneFinger(touch) {
        touchMode = "pan";
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
        touchLastX = touch.clientX;
        touchLastY = touch.clientY;
        touchStartZoom = targetZoom;
        touchStartOffsetX = targetOffsetX;
        touchStartOffsetY = targetOffsetY;
        viewer.classList.add("dragging");
    }

    function startPinch(touches) {
        const a = touches[0];
        const b = touches[1];
        const rect = viewer.getBoundingClientRect();
        const center = touchCenter(a, b, rect);

        touchMode = "pinch";
        touchPinchDistance = Math.max(1, touchDistance(a, b));
        touchStartZoom = targetZoom;
        touchStartOffsetX = targetOffsetX;
        touchStartOffsetY = targetOffsetY;

        touchPinchCenterPdfX =
            (center.x - touchStartOffsetX) / Math.max(touchStartZoom, 0.001);
        touchPinchCenterPdfY =
            (center.y - touchStartOffsetY) / Math.max(touchStartZoom, 0.001);
    }

    viewer.addEventListener("touchstart", function(e) {
        if (!pdfDocument) return;
        if (e.target.closest?.(".mobile-toolbar")) return;

        /* Всегда отключаем браузерные жесты внутри области PDF. */
        e.preventDefault();

        if (e.touches.length >= 2) {
            startPinch(e.touches);
            return;
        }

        if (e.touches.length === 1) {
            startOneFinger(e.touches[0]);
        }
    }, { passive: false });

    viewer.addEventListener("touchmove", function(e) {
        if (!pdfDocument) return;
        if (e.target.closest?.(".mobile-toolbar")) return;

        e.preventDefault();

        /* Два пальца: zoom вокруг точки между пальцами. */
        if (e.touches.length >= 2) {
            if (touchMode !== "pinch") {
                startPinch(e.touches);
            }

            const a = e.touches[0];
            const b = e.touches[1];
            const rect = viewer.getBoundingClientRect();
            const center = touchCenter(a, b, rect);
            const distance = Math.max(1, touchDistance(a, b));

            targetZoom = Math.max(
                MIN_ZOOM,
                Math.min(
                    MAX_ZOOM,
                    touchStartZoom * distance / touchPinchDistance
                )
            );

            targetOffsetX =
                center.x - touchPinchCenterPdfX * targetZoom;
            targetOffsetY =
                center.y - touchPinchCenterPdfY * targetZoom;

            startAnimation();

            clearTimeout(renderTimer);
            renderTimer = setTimeout(renderHighQualityPage, 180);
            return;
        }

        /* Один палец: обычный pan. Никакого перелистывания. */
        if (e.touches.length === 1) {
            const t = e.touches[0];

            if (touchMode !== "pan") {
                startOneFinger(t);
            }

            const dx = t.clientX - touchStartX;
            const dy = t.clientY - touchStartY;

            /* Панорамирование разрешено только при увеличении PDF. */
            if (targetZoom > 1.001) {
                targetOffsetX = touchStartOffsetX + dx;
                targetOffsetY = touchStartOffsetY + dy;
                startAnimation();
            }

            touchLastX = t.clientX;
            touchLastY = t.clientY;
        }
    }, { passive: false });

    viewer.addEventListener("touchend", function(e) {
        if (!pdfDocument) return;
        e.preventDefault();

        /* Если после pinch остался один палец — начинаем новый pan
           именно с его текущего положения, без скачка страницы. */
        if (e.touches.length === 1) {
            startOneFinger(e.touches[0]);
            return;
        }

        if (e.touches.length === 0) {
            touchMode = "none";
            viewer.classList.remove("dragging");
        }
    }, { passive: false });

    viewer.addEventListener("touchcancel", function() {
        touchMode = "none";
        viewer.classList.remove("dragging");
    }, { passive: false });

    /* =====================================
       ВЫБРАТЬ ДРУГОЙ PDF
       ===================================== */

    changeFile.addEventListener(
        "click",
        function () {

            pdfDocument = null;


            pageNames = {};

            pageCategories = {};


            pinnedPages =
                new Set();


            currentPdfName =
                "";


            currentPage =
                1;


            currentCategory =
                "TAXI";


            currentSearch =
                "";


            chartSearch.value =
                "";


            categoryButtons.forEach(
                function (button) {

                    button.classList.toggle(

                        "active",

                        button.dataset.category ===
                        "TAXI"

                    );

                }
            );


            rotation = 0;
            automaticRotation = 0;
            manualRotation = 0;

            inverted = false;

            zoom = 1;

            renderedZoom = 1;

            targetZoom = 1;

            offsetX = 0;

            offsetY = 0;

            targetOffsetX = 0;

            targetOffsetY = 0;


            if (animationFrame) {

                cancelAnimationFrame(
                    animationFrame
                );

                animationFrame = null;

            }


            clearTimeout(
                renderTimer
            );


            ctx.clearRect(

                0,
                0,

                canvas.width,
                canvas.height

            );


            canvas.width = 0;

            canvas.height = 0;


            canvas.style.filter =
                "none";


            pageList.innerHTML =
                "";


            pagesTitle.textContent =
                "TAXI";


            invertButton.textContent =
                "☾ Ночная версия";


            pageBlock.style.display =
                "none";

            const app =
                document.getElementById("app");

            if (app) {
                app.classList.add("initial");
                app.classList.remove("mobile-menu-open");
            }

            uploadBlock.style.display =
                "block";


            chartSelect.value = "";
            airportInput.value = "";
            if (airportSuggestions) {
                airportSuggestions.innerHTML = "";
                airportSuggestions.style.display = "none";
            }

            chartSelect.innerHTML =
                '<option value="">Введите код аэропорта</option>';

            chartSelect.disabled = true;
            openChartButton.disabled = true;

            loadCharts();

        }
    );



    /* =====================================
       ИЗМЕНЕНИЕ РАЗМЕРА ОКНА
       ===================================== */

    let resizeTimer = null;


    window.addEventListener(
        "resize",
        function () {

            if (!pdfDocument) {

                return;
            }


            clearTimeout(
                resizeTimer
            );


            resizeTimer =
                setTimeout(
                    function () {

                        showPage(
                            currentPage
                        );

                    },
                    150
                );

        }
    );


</script>



</body>

</html>