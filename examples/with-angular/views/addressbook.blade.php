<!doctype html>
<html data-ng-app="app" id="ng-app" xmlns:ng="http://angularjs.org">
<head>
<base href="/">
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/angularjs/1.3.0/angular.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/angularjs/1.3.0/angular-route.min.js"></script>
<script src="/app.js"></script>
<style>
body { font-family: helvetica, arial, sans-serif; }
input[type="text"] { font-size: 14px; padding: 5px 10px; }
#info { background: #d9edf7; padding: 20px; margin-bottom: 20px; }
</style>
<title>With AngularJS example</title>
</head>
<body>
<div id="info">This will show the result of each save and any errors encountered.</div>
<div data-ng-view=""></div>
</body>
</html>
