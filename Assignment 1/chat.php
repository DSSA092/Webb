<?php
// 1. DATABASE CONNECTION
$servername = "localhost";
$username   = "root";
$password   = "Admin@1234";
$dbname     = "web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    exit("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// 2. CREATE CHAT TABLE
$table_sql = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($table_sql)) {
    http_response_code(500);
    exit("Could not create chat table.");
}

// 3. SEND MESSAGE THROUGH AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json; charset=UTF-8");

    $sender  = trim($_POST["sender"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($sender === "" || $message === "") {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Please enter your name and message."
        ]);
        exit;
    }

    if (strlen($sender) > 30 || strlen($message) > 2000) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Your input is too long."
        ]);
        exit;
    }

    $sql = "INSERT INTO chat_messages (sender, message) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $sender, $message);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Message could not be saved."
        ]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// 4. FETCH MESSAGES THROUGH AJAX
if (isset($_GET["fetch"])) {
    header("Content-Type: application/json; charset=UTF-8");

    $sql = "SELECT sender, message, reg_date
            FROM chat_messages
            ORDER BY id DESC
            LIMIT 200";

    $result = $conn->query($sql);
    $messages = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }

    echo json_encode(array_reverse($messages), JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#090909">
    <title>Chatspace — Monochrome Glass</title>
    <style>
        :root {
            color-scheme: dark;
            --white: #f7f7f7;
            --muted: #a2a2a2;
            --glass: rgba(255,255,255,.055);
            --stroke: rgba(255,255,255,.13);
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh; margin: 0; padding: 30px 16px;
            display: grid; place-items: center; color: var(--white);
            background: radial-gradient(ellipse at 15% 5%, #292929 0, transparent 38%),
                        radial-gradient(ellipse at 90% 95%, #202020 0, transparent 40%), #080808;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .orb { display: none; }
        .app-shell {
            width: min(100%, 780px); padding: 1px; overflow: hidden;
            border: 1px solid rgba(255,255,255,.17); border-radius: 28px;
            background: linear-gradient(145deg, rgba(255,255,255,.22), rgba(255,255,255,.025) 45%, rgba(255,255,255,.12));
            box-shadow: 0 35px 100px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.025);
            animation: appear .45s ease-out both;
        }
        .chat-card {
            overflow: hidden; border-radius: 27px;
            background: linear-gradient(145deg, rgba(29,29,29,.94), rgba(12,12,12,.97));
            backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px);
        }
        .topbar {
            display: flex; align-items: center; gap: 15px; padding: 25px 28px;
            border-bottom: 1px solid var(--stroke); background: rgba(255,255,255,.025);
        }
        .app-icon {
            display: grid; place-items: center; flex: 0 0 48px; width: 48px; height: 48px;
            border: 1px solid rgba(255,255,255,.24); border-radius: 16px;
            color: #090909; background: linear-gradient(145deg, #fff, #bdbdbd);
            font-size: 25px; box-shadow: 0 5px 22px rgba(255,255,255,.1);
        }
        .heading { min-width: 0; flex: 1; }
        h1 { margin: 0; font-size: 23px; letter-spacing: -.7px; font-weight: 760; }
        .subtitle { margin: 5px 0 0; color: #a4a4a4; font-size: 13px; }
        .online-pill {
            display: inline-flex; align-items: center; gap: 8px; padding: 8px 11px;
            border: 1px solid rgba(255,255,255,.13); border-radius: 999px;
            color: #e5e5e5; background: rgba(255,255,255,.055); font-size: 11px; font-weight: 650; white-space: nowrap;
        }
        .online-dot { width: 7px; height: 7px; border-radius: 50%; background: #fff; box-shadow: 0 0 10px rgba(255,255,255,.8); }
        .identity { padding: 20px 28px 0; }
        .field-label { display: block; margin: 0 0 8px 1px; color: #a5a5a5; font-size: 11px; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
        input {
            width: 100%; min-width: 0; border: 1px solid rgba(255,255,255,.13); border-radius: 14px;
            outline: none; padding: 13px 15px; color: #fff; background: rgba(255,255,255,.045);
            font: inherit; font-size: 14px; transition: border-color .18s, box-shadow .18s, background .18s;
        }
        input::placeholder { color: #777; }
        input:focus { border-color: rgba(255,255,255,.55); background: rgba(255,255,255,.075); box-shadow: 0 0 0 3px rgba(255,255,255,.055); }
        .chatbox {
            display: flex; flex-direction: column; gap: 14px; height: min(55vh, 490px); min-height: 260px;
            overflow-y: auto; overscroll-behavior: contain; margin: 18px 20px 12px; padding: 20px 18px;
            border: 1px solid rgba(255,255,255,.09); border-radius: 20px;
            background: radial-gradient(circle at 1px 1px, rgba(255,255,255,.07) 1px, transparent 0) 0 0 / 19px 19px,
                        rgba(255,255,255,.018);
            scrollbar-width: thin; scrollbar-color: #555 transparent;
        }
        .chatbox::-webkit-scrollbar { width: 6px; }
        .chatbox::-webkit-scrollbar-thumb { background: #555; border-radius: 20px; }
        .empty-state { display: grid; place-content: center; flex: 1; min-height: 150px; color: #929292; text-align: center; font-size: 13px; line-height: 1.7; }
        .empty-state span { display: block; font-size: 28px; margin-bottom: 7px; color: #ddd; }
        .chat-message {
            align-self: flex-start; width: fit-content; max-width: min(82%, 520px); padding: 12px 15px 10px;
            border: 1px solid rgba(255,255,255,.13); border-radius: 5px 18px 18px 18px;
            background: rgba(255,255,255,.075); box-shadow: 0 8px 24px rgba(0,0,0,.18);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            overflow-wrap: anywhere; animation: message-in .2s ease-out both;
        }
        .chat-message.own {
            align-self: flex-end; border-color: rgba(255,255,255,.5); border-radius: 18px 5px 18px 18px;
            color: #090909; background: linear-gradient(145deg, #f5f5f5, #cfcfcf); box-shadow: 0 8px 26px rgba(255,255,255,.09);
        }
        .chat-message strong { display: block; margin-bottom: 5px; color: #fff; font-size: 12px; font-weight: 750; }
        .chat-message.own strong { color: #343434; }
        .chat-message p { margin: 0; color: #f4f4f4; font-size: 14px; line-height: 1.55; white-space: pre-wrap; }
        .chat-message.own p { color: #101010; }
        .chat-message small { display: block; margin-top: 8px; color: #999; font-size: 10px; }
        .chat-message.own small { color: #555; }
        .composer { display: flex; align-items: center; gap: 10px; padding: 0 20px 16px; }
        .composer input { flex: 1; height: 51px; border-radius: 16px; padding-inline: 16px; }
        button {
            display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-width: 105px; height: 51px; padding: 0 18px;
            border: 1px solid rgba(255,255,255,.7); border-radius: 16px; color: #090909;
            background: linear-gradient(145deg, #fff, #c9c9c9); box-shadow: 0 7px 22px rgba(255,255,255,.09);
            font: inherit; font-size: 13px; font-weight: 750; cursor: pointer; transition: transform .18s, box-shadow .18s, opacity .18s;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(255,255,255,.15); }
        button:active { transform: translateY(0) scale(.98); }
        button:disabled { opacity: .6; cursor: wait; transform: none; }
        button svg { width: 16px; height: 16px; }
        .footer { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 37px; padding: 0 18px 12px; color: #858585; text-align: center; font-size: 11px; }
        .footer-dot { width: 6px; height: 6px; border-radius: 50%; background: #666; }
        .footer.is-connected .footer-dot { background: #eee; box-shadow: 0 0 8px rgba(255,255,255,.5); }
        .footer.is-error { color: #ccc; }
        .footer.is-error .footer-dot { background: #fff; }
        @keyframes appear { from { opacity: 0; transform: translateY(10px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes message-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 560px) {
            body { padding: 12px 8px; }
            .app-shell { border-radius: 23px; }
            .chat-card { border-radius: 22px; }
            .topbar { padding: 18px 15px; gap: 11px; }
            .app-icon { flex-basis: 42px; width: 42px; height: 42px; border-radius: 14px; }
            h1 { font-size: 19px; } .subtitle { font-size: 11px; }
            .online-pill { padding: 7px 8px; font-size: 10px; }
            .identity { padding: 16px 15px 0; }
            .chatbox { height: 52vh; min-height: 230px; margin: 14px 8px 10px; padding: 13px 9px; }
            .chat-message { max-width: 90%; }
            .composer { padding: 0 8px 10px; gap: 7px; }
            .composer input { height: 47px; padding-inline: 12px; }
            button { min-width: 48px; width: 48px; height: 47px; padding: 0; }
            .send-label { display: none; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
    <div class="orb orb-one"></div>
    <div class="orb orb-two"></div>

    <main class="app-shell">
        <section class="chat-card" aria-label="Chat application">
            <header class="topbar">
                <div class="app-icon" aria-hidden="true">✳</div>
                <div class="heading">
                    <h1>Chatspace</h1>
                    <p class="subtitle">A quieter place to catch up.</p>
                </div>
                <div class="online-pill"><span class="online-dot"></span> Live chat</div>
            </header>

            <div class="identity">
                <label class="field-label" for="sender">Your name</label>
                <input
                    type="text"
                    id="sender"
                    placeholder="How should friends call you?"
                    maxlength="30"
                    autocomplete="nickname"
                    required
                >
            </div>

            <div id="chatbox" class="chatbox" role="log" aria-live="polite" aria-label="Chat messages">
                <div class="empty-state"><span>✳</span>Loading your conversation…</div>
            </div>

            <form id="chat-form" class="composer">
                <input
                    type="text"
                    id="message"
                    placeholder="Write a message…"
                    maxlength="2000"
                    autocomplete="off"
                    aria-label="Message"
                    required
                >
                <button type="submit" id="send-button" aria-label="Send message">
                    <span class="send-label">Send</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 12 20 4l-5 16-3-7-8-1Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="m12 13 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </button>
            </form>

            <div id="status" class="footer" role="status">
                <span class="footer-dot"></span>
                <span id="status-text">Connecting to chat…</span>
            </div>
        </section>
    </main>

<script>
    const chatbox = document.getElementById("chatbox");
    const sender = document.getElementById("sender");
    const message = document.getElementById("message");
    const form = document.getElementById("chat-form");
    const status = document.getElementById("status");
    const statusText = document.getElementById("status-text");
    const sendButton = document.getElementById("send-button");

    // Remember the display name so your messages stay on the right after refresh.
    try {
        sender.value = localStorage.getItem("chatspace_display_name") || "";
    } catch (error) {}

    sender.addEventListener("input", function () {
        try { localStorage.setItem("chatspace_display_name", sender.value.trim()); } catch (error) {}
        loadMessages();
    });

    function setStatus(text, state = "connected") {
        statusText.textContent = text;
        status.classList.toggle("is-connected", state === "connected");
        status.classList.toggle("is-error", state === "error");
    }

    async function loadMessages() {
        try {
            const response = await fetch("chat.php?fetch=1", { cache: "no-store" });
            if (!response.ok) throw new Error("Could not load messages");
            const data = await response.json();

            const atBottom = chatbox.scrollHeight - chatbox.scrollTop - chatbox.clientHeight < 50;
            const fragment = document.createDocumentFragment();

            if (data.length === 0) {
                const empty = document.createElement("div");
                empty.className = "empty-state";
                const icon = document.createElement("span");
                icon.textContent = "☁";
                const text = document.createElement("div");
                text.textContent = "It's quiet here… Start the conversation.";
                empty.append(icon, text);
                fragment.appendChild(empty);
            } else {
                data.forEach(function (chat) {
                    const item = document.createElement("div");
                    item.className = "chat-message";
                    if (sender.value.trim() && chat.sender.trim().toLowerCase() === sender.value.trim().toLowerCase()) {
                        item.classList.add("own");
                    }

                    const name = document.createElement("strong");
                    name.textContent = chat.sender;

                    const text = document.createElement("p");
                    text.textContent = chat.message;

                    const time = document.createElement("small");
                    time.textContent = chat.reg_date;

                    item.append(name, text, time);
                    fragment.appendChild(item);
                });
            }

            chatbox.replaceChildren(fragment);
            if (atBottom) chatbox.scrollTop = chatbox.scrollHeight;
            setStatus("Connected — messages update automatically");
        } catch (error) {
            setStatus("Connection issue — retrying…", "error");
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const name = sender.value.trim();
        const text = message.value.trim();

        if (!name || !text) {
            setStatus("Enter your name and a message to send.", "error");
            return;
        }

        sendButton.disabled = true;
        try {
            const response = await fetch("chat.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ sender: name, message: text })
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || "Message could not be sent.");
            }

            message.value = "";
            await loadMessages();
            message.focus();
        } catch (error) {
            setStatus(error.message || "Message could not be sent.", "error");
        } finally {
            sendButton.disabled = false;
        }
    });

    loadMessages();
    setInterval(loadMessages, 2000);
</script>
</body>
</html>
