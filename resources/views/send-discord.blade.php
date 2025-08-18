{{-- <div>
    <!-- An unexamined life is not worth living. - Socrates -->
</div> --}}


<!DOCTYPE html>
<html>
<head>
    <title>Send Discord Message</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
        input, textarea, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { background: #5865f2; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Send Message to Discord</h1>

    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="/send-discord">
        @csrf
        <input type="text" 
               name="channel_id" 
               placeholder="Discord Channel ID" 
               required>
        
        <textarea name="message" 
                  placeholder="Your message here..." 
                  rows="4" 
                  required></textarea>
        
        <button type="submit">Send to Discord</button>
    </form>

    {{-- <p><small>Make sure your Python bot is running on http://localhost:8001</small></p> --}}
</body>
</html>