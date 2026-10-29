<?php
// 1. DATABASE CONNECTION
$servername = "localhost";
$username   = "root";
$password   = "Admin@1234";
$dbname     = "web";

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);

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
    header("Content-Type: application/json");

    $sender = trim($_POST["sender"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($sender === "" || $message === "") {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Please enter your name and message."
        ]);
        exit;
    }

    if (
        strlen($sender) > 120 ||
        strlen($message) > 8000
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Your input is too long."
        ]);
        exit;
    }

    $sql = "INSERT INTO chat_messages (sender, message)
            VALUES (?, ?)";

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

    // Display messages from oldest to newest
    echo json_encode(
        array_reverse($messages),
        JSON_UNESCAPED_UNICODE
    );

    $conn->close();
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Simple Chat Application</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #eef2ff;
        }

        .container {
            width: 100%;
            max-width: 500px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 15px #00000015;
        }

        h2 {
            text-align: center;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            margin-bottom: 20px;
        }

        input, button {
            padding: 12px;
            font-size: 15px;
            border-radius: 8px;
        }

        input {
            width: 100%;
            border: 1px solid #ddd;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
        }

        #sender {
            margin-bottom: 12px;
        }

        #chatbox {
            height: 350px;
            overflow-y: auto;
            padding: 12px;
            margin-bottom: 12px;
            background: #f8fafc;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .chat-message {
            padding: 10px;
            margin-bottom: 10px;
            background: #dbeafe;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        .chat-message strong {
            color: #1d4ed8;
        }

        .chat-message p {
            margin: 6px 0;
            white-space: pre-wrap;
        }

        .chat-message small {
            color: #666;
            font-size: 11px;
        }

        .message-row {
            display: flex;
            gap: 8px;
        }

        .message-row input {
            flex: 1;
            min-width: 0;
        }

        button {
            border: none;
            color: white;
            background: #2563eb;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        #status {
            color: #666;
            font-size: 12px;
            text-align: center;
            margin-top: 12px;
        }

        @media (max-width: 400px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 14px;
                margin: 10px auto;
            }

            #chatbox {
                height: 55vh;
            }

            .message-row {
                flex-wrap: wrap;
            }

            .message-row button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h2>💬 Simple Chat</h2>
    <p class="subtitle">Chat with your friends</p>

    <input
        type="text"
        id="sender"
        placeholder="Enter your name"
        maxlength="30"
        autocomplete="nickname"
        required
    >

    <div id="chatbox">Loading messages...</div>

    <form id="chat-form" class="message-row">
        <input
            type="text"
            id="message"
            placeholder="Type a message..."
            maxlength="2000"
            autocomplete="off"
            required
        >

        <button type="submit" id="send-button">
            Send
        </button>
    </form>

    <div id="status">Connecting to chat...</div>

</div>

<script>
    const chatbox = document.getElementById("chatbox");
    const sender = document.getElementById("sender");
    const message = document.getElementById("message");
    const form = document.getElementById("chat-form");
    const statusText = document.getElementById("status");
    const sendButton = document.getElementById("send-button");

    // Load messages from the database
    async function loadMessages() {
        try {
            const response = await fetch("chat.php?fetch=1", {
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error("Could not load messages");
            }

            const data = await response.json();

            // Remember whether the user is at the bottom
            const atBottom =
                chatbox.scrollHeight -
                chatbox.scrollTop -
                chatbox.clientHeight < 50;

            // Create messages without inserting raw HTML
            const fragment = document.createDocumentFragment();

            data.forEach(function (chat) {
                const item = document.createElement("div");
                item.className = "chat-message";

                const name = document.createElement("strong");
                name.textContent = chat.sender;

                const text = document.createElement("p");
                text.textContent = chat.message;

                const time = document.createElement("small");
                time.textContent = chat.reg_date;

                item.append(name, text, time);
                fragment.appendChild(item);
            });

            chatbox.replaceChildren(fragment);

            if (atBottom) {
                chatbox.scrollTop = chatbox.scrollHeight;
            }

            statusText.textContent = "Connected to chat";

        } catch (error) {
            statusText.textContent =
                "Unable to connect. Checking again...";
        }
    }

    // Send a message without reloading the page
    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const name = sender.value.trim();
        const text = message.value.trim();

        if (!name || !text) {
            alert("Please enter your name and message.");
            return;
        }

        sendButton.disabled = true;

        try {
            const response = await fetch("chat.php", {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    sender: name,
                    message: text
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.error || "Message could not be sent."
                );
            }

            message.value = "";
            await loadMessages();
            message.focus();

        } catch (error) {
            alert(error.message);
        } finally {
            sendButton.disabled = false;
        }
    });

    // Load messages when the page opens
    loadMessages();

    // Check for new messages every 2 seconds
    setInterval(loadMessages, 2000);
</script>

</body>
</html>