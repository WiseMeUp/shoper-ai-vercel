<!doctype html>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="referrer" content="origin">
        <script src="https://dcsaascdn.net/js/dc-sdk-1.0.5.min.js"></script>

        <script>
            (function () {
                'use strict';

                var styles;

                if (localStorage.getItem('styles')) {
                    styles = JSON.parse(localStorage.getItem('styles'));
                    injectStyles(styles);
                }

                window.shopAppInstance = new ShopApp(function (app) {
                    app.init(null, function (params, app) {
                        if (localStorage.getItem('styles') === null) {
                            injectStyles(params.styles);
                        }
                        localStorage.setItem('styles', JSON.stringify(params.styles));

                        app.show(null, function () {
                            app.adjustIframeSize();
                        });
                    }, function (errmsg, app) {
                        alert(errmsg);
                    });
                }, true);

                function injectStyles (styles) {
                    var i;
                    var el;
                    var sLength;

                    sLength = styles.length;
                    for (i = 0; i < sLength; ++i) {
                        el = document.createElement('link');
                        el.rel = 'stylesheet';
                        el.type = 'text/css';
                        el.href = styles[i];
                        document.getElementsByTagName('head')[0].appendChild(el);
                    }
                }
            }());
        </script>
    </head>
    <body>
        <main class="rwd-layout-width rwd-layout-container">
            <section class="rwd-layout-col-12">
                <a href="/admin" class="btn btn-primary" style="margin-bottom: 15px; display: inline-block;">Konfiguracja</a>
                <div class="edition-form">
                    <p>Kategorie sklepu (<?php echo App::escapeHtml($categories->count); ?>):</p>
                    <ul>
                        <?php
                        foreach ($categories as $c) {
                            // array access
                            if (isset($c['translations'][$_locale])) {
                                ?>
                                <li>
                                    <?php
                                    // object property access
                                    echo App::escapeHtml($c->translations->$_locale->name); ?>

                                    (id: <?php echo App::escapeHtml($c['category_id']); ?>)
                                </li>
                            <?php
                            }
                        }
                        ?>
                    </ul>
                    <div class="clearfix"></div>
                </div>
            </section>
        </main>
    </body>
</html>
