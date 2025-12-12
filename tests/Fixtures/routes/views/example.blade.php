<!DOCTYPE html>
<html>
<head>
    <title>Example</title>
</head>
<body>
    <nav>
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('about') }}">About</a>
    </nav>

    <main>
        <h1>Welcome</h1>
        <p>This is an example Blade template.</p>
    </main>

    <footer>
        <a href="{{ route('contact') }}">Contact Us</a>
    </footer>
</body>
</html>
