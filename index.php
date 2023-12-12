<?php

$openai_api_key = '';

set_time_limit(0);

function generateRandomFileName($extension) {
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    return "{$randomString}_{$timestamp}.{$extension}";
}

$error = '';
$content = '';
$additional_info = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["image"])) {
    $additional_info = isset($_POST['additional_info']) ? trim($_POST['additional_info']) : '';
    $target_dir = "images/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir);
    }
    $imageFileType = strtolower(pathinfo(basename($_FILES["image"]["name"]), PATHINFO_EXTENSION));

    if (!in_array($imageFileType, ['jpeg', 'jpg', 'png'])) {
        $error = '不正なファイル形式です。JPEGまたはPNGファイルのみアップロード可能です。';
    } else {
        $target_file = $target_dir . generateRandomFileName($imageFileType);

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/$target_file";
            
            $prompt = "添付する画像の商品をメルカリに出品しようとしています
            商品のタイトルと説明文を売れやすいように考えてください
            ただし、以下の制約に注意してください
            
            ・商品のタイトルは最大130文字まで
            ・商品の説明文は最大3,000文字まで
            ・誤解のないよう商品の正確な商品名がわかるように書いてください
            ・説明文には検索されるであろうワードをハッシュタグ形式で最低10個末尾につけてください";
            if (!empty($additional_info)) {
                $prompt .= PHP_EOL . "画像の捕捉情報：{$additional_info}";
            }
            $data = [
                'model' => 'gpt-4-vision-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => $image_url
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 4000
            ];
    
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                "Authorization: Bearer {$openai_api_key}"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $error = 'CURL Error: ' . curl_error($ch);
            }
            curl_close($ch);
    
            $responseArray = json_decode($response, true);
            if (isset($responseArray['choices'][0]['message']['content'])) {
                $content = $responseArray['choices'][0]['message']['content'];
            } else {
                $content = "API接続エラー";
                $error = json_encode($responseArray);
            }
        } else {
            $error = "画像アップロードエラー";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIで年末大掃除</title>
    <!-- Bootstrap CSSの追加 -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <!-- カスタムスタイルの追加 -->
    <style>
        body {
            padding-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-control-file {
            margin-top: 15px;
        }
        .btn-primary {
            margin-top: 10px;
        }
        textarea {
            width: 100%;
            margin-top: 15px;
        }
        .error-message {
            color: #dc3545; /* Bootstrapのデフォルトエラーカラー */
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <p>断捨離する物の写真をアップロード</p>
        <form action="." method="post" enctype="multipart/form-data">
            <div class="form-group">
                <input type="file" name="image" id="image" class="form-control-file">
            </div>
            <div class="form-group">
                <label for="additional_info">補足情報（任意）:</label>
                <textarea name="additional_info" id="additional_info" class="form-control" rows="3" placeholder="商品についての補足情報を入力してください。"></textarea>
            </div>
            <div class="form-group">
                <input type="submit" value="アップロード" name="submit" class="btn btn-primary">
            </div>
        </form>

        <?php if (isset($content)): ?>
            <textarea rows="10" class="form-control"><?php echo htmlspecialchars($content); ?></textarea>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p class="error-message">Error: <?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JSの追加 -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
