<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
	<meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Pie API - Infrastructure for Small Businesses" />
	<meta name="author" content="Akhil Mantripragada" />

  <!-- GOOGLE FONT -->
  <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Inter" />
  <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Epilogue:100,200,300,400,500,600,700,800,900" />

  <!-- FAVICON -->
  <link href="/images/icon.svg" rel="shortcut icon">
  <link rel="stylesheet" href="{{ mix('/css/app.css') }}">
</head>
<body class="h-full overflow-hidden font-inter">
	<!-- BEGIN #app -->
	<div id="app" class="h-full overflow-hidden">
    <router-view/>
	</div>
	<!-- END #app -->
  <script src="{{ mix('/js/main.js') }}"></script>
</body>
</html>
