<?php
// Enable error reporting for debugging during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// --- ALWAYS require the autoloader first ---
require 'vendor/autoload.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;

if ($action) {
    switch ($action) {
        case 'history':
            $history = [];
            if (isset($_SESSION['conversations'])) {
                foreach ($_SESSION['conversations'] as $id => $convo) {
                    $history[] = [
                        'id' => $id,
                        'title' => $convo['title'] ?? 'New Chat'
                    ];
                }
            }
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        case 'get_conversation':
            $convo_id = $_GET['id'] ?? null;
            if ($convo_id && isset($_SESSION['conversations'][$convo_id])) {
                $conversation = $_SESSION['conversations'][$convo_id];
                foreach ($conversation['chat_history'] as &$message) {
                    if ($message['role'] === 'model') {
                        $message['parts'][0]['text'] = formatResponse($message['parts'][0]['text']);
                    }
                }
                unset($message);
                echo json_encode(['success' => true, 'conversation' => $conversation]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Conversation not found.']);
            }
            break;

        case 'delete_conversation':
            $convo_id = $_GET['id'] ?? null;
            if ($convo_id && isset($_SESSION['conversations'][$convo_id])) {
                unset($_SESSION['conversations'][$convo_id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Conversation not found.']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
            break;
    }
    exit;
}

// --- Main Chat Logic (handles POST requests without an 'action' parameter) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method for chat.']);
    exit;
}

$question = $_POST['question'] ?? '';
$learningLevel = $_POST['learningLevel'] ?? 'Understanding';

$conversation_id = $_POST['conversation_id'] ?? null;

// If no ID, create a new conversation
if (!$conversation_id) {
    $conversation_id = uniqid();
    $_SESSION['conversations'][$conversation_id] = [
        'title' => 'New Chat on ' . date('Y-m-d'),
        'chat_history' => []
    ];
}

// Ensure the conversation exists before proceeding
if (!isset($_SESSION['conversations'][$conversation_id])) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation ID.']);
    exit;
}

function prepareFileParts($file, $user_question) {
    $filePath = $file['tmp_name'];
    $fileType = mime_content_type($filePath);
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed_types = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'bmp'  => 'image/bmp',
        'webp' => 'image/webp',
    ];

    if (!in_array($extension, array_keys($allowed_types))) {
        // Let's be more generic in the error message now
        throw new Exception("Unsupported file type: {$extension}.");
    }

    // Double-check MIME type
    if (!in_array($fileType, $allowed_types)) {
         // Allow for some variation in MIME types reported by servers
        if ($extension !== 'docx' || $fileType !== 'application/zip') {
            throw new Exception("File content does not match its extension ({$extension} vs {$fileType}).");
        }
    }

    // Handle images
    if (strpos($fileType, 'image/') === 0) {
        $fileData = file_get_contents($filePath);
        if ($fileData === false) {
            throw new Exception("Could not read the image file '{$originalName}'.");
        }
        $base64Data = base64_encode($fileData);

        return [
            ['inline_data' => ['mime_type' => $fileType, 'data' => $base64Data]],
            ['text' => $user_question]
        ];
    }

    $text = '';
    switch ($extension) {
        case 'txt':
            $text = file_get_contents($filePath);
            break;
        case 'pdf':
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            break;
        case 'docx':
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $textExtractor = new \PhpOffice\PhpWord\Shared\Html();
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach($element->getElements() as $textElement) {
                             if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $textElement->getText() . ' ';
                            }
                        }
                    }
                }
            }
            break;
        case 'pptx':
            $phpPresentation = \PhpOffice\PhpPresentation\IOFactory::load($filePath);
            foreach ($phpPresentation->getAllSlides() as $slide) {
                foreach ($slide->getShapeCollection() as $shape) {
                    if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                        $text .= $shape->getPlainText() . "\n\n";
                    }
                }
            }
            break;
    }

    if (empty($text)) {
        throw new Exception("Could not extract any text from the file '{$originalName}'. It might be empty, image-based, or corrupted.");
    }

    // Truncate to a reasonable length to avoid excessive API costs/limits
    $maxLength = 20000; // Approx 5000 tokens
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength) . "\n\n... [File content truncated] ...\n\n";
    }

    $combined_text = "Context from uploaded file '{$originalName}':\n---\n{$text}\n---\n\nUser's question: {$user_question}";
    return [
        ['text' => $combined_text]
    ];
}


function formatResponse($text) {
    // --- Protect LaTeX from Parsedown ---    
    // Use a callback to handle different LaTeX delimiters and protect them.
    $text = preg_replace_callback(
        '/(\$\$|\\\\\[|\\\\\(|\$)(.*?)(\$\$|\\\\\]|\\\\\)|(?<![0-9])\$(?![0-9]))/s',
        function ($matches) {
            // Only match valid pairs, e.g., $$ with $$
            $delimiters = [
                '$$' => '$$',
                '\\[' => '\\]',
                '\\(' => '\\)',
                '$' => '$'
            ];
            if (isset($delimiters[$matches[1]]) && $delimiters[$matches[1]] === $matches[3]) {
                // Use placeholders that Parsedown won't touch
                    // IMPORTANT: The placeholder now includes the original delimiter so we can restore it perfectly.
                return '@@LATEX_PLACEHOLDER_START@@' . base64_encode($matches[1]) . '@@' . $matches[2] . '@@LATEX_PLACEHOLDER_END@@' . base64_encode($matches[3]) . '@@';
            }
            // Not a valid pair, return original text
            return $matches[0];
        },
        $text
    );

    $Parsedown = new Parsedown();
    // This is the key change: it prevents Parsedown from wrapping every line in <p> tags.
    $Parsedown->setBreaksEnabled(true);
    $html = $Parsedown->text($text);

    // Restore the original LaTeX delimiters by decoding them from the placeholders
    $html = preg_replace_callback(
        '/@@LATEX_PLACEHOLDER_START@@(.*?)@@(.*?)@@LATEX_PLACEHOLDER_END@@(.*?)@@/s',
        function ($matches) {
            $open = base64_decode($matches[1]);
            $content = $matches[2];
            $close = base64_decode($matches[3]);
            return $open . $content . $close;
        },
        $html
    );

    // --- Final HTML Cleanup ---
    // Remove <p> tags from inside <li> tags to prevent unwanted line breaks for inline math.
    // This is more robust and handles potential whitespace.
    $html = preg_replace('/<li(>|\s[^>]*>)\s*<p>(.*?)<\/p>\s*<\/li>/s', '<li$1>$2</li>', $html);


    return $html;
}

try {
    // NOTE: For a production environment, use environment variables or a secure configuration file.
    define('GEMINI_API_KEY', 'AIzaSyCf3IWMndg1K6uIwT6kYDDtLrGi2-PyWIo');

    if (GEMINI_API_KEY === 'YOUR_ACTUAL_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API Key is not configured on the server.');
    }

    $user_message_parts = [];

    // Check for file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $user_message_parts = prepareFileParts($_FILES['attachment'], $question);
    } else {
        $user_message_parts[] = ['text' => $question];
    }

    // Basic validation
    $is_empty = empty($user_message_parts) || (count($user_message_parts) === 1 && empty(trim($user_message_parts[0]['text'])));
    if ($is_empty) {
        echo json_encode(['success' => false, 'error' => 'Question is missing or file content is empty.']);
        exit;
    }

    $chat_history = $_SESSION['conversations'][$conversation_id]['chat_history'];

    // Add the new user message (with its parts) to the history
    $chat_history[] = ["role" => "user", "parts" => $user_message_parts];

    // System prompt
    $system_prompt = <<<PROMPT
# Adaptive AI Tutor System Prompt

You are an expert AI tutor designed to facilitate deep learning across any subject. Your goal is not just to provide answers, but to guide learners toward understanding through adaptive, personalized instruction.

## Core Philosophy

- **Learning > Answers**: Prioritize understanding over quick solutions
- **Adaptive**: Continuously adjust to the learner's needs
- **Socratic**: Use questions to guide discovery when appropriate
- **Encouraging**: Build confidence and maintain engagement
- **Metacognitive**: Help learners understand their own thinking

---

## PHASE 1: ASSESS THE LEARNER

Before responding, analyze the learner's message. The user has indicated a desired learning goal based on Bloom's Taxonomy: **{$learningLevel}**. Use this as a starting point, but adapt based on your analysis of their actual message.

### A. Knowledge State Indicators

**General Proficiency:**
- **Novice**: Vague questions, missing vocabulary, fundamental confusion
- **Developing**: Partial understanding, specific confusion points, some correct terminology
- **Proficient**: Detailed questions, mostly correct understanding, seeking nuance
- **Expert**: Deep questions, looking for edge cases or advanced applications

**Bloom's Taxonomy Level** (Cognitive Dimension):
Identify which level(s) the learner is operating at or needs to reach:

1.  **Remember**: Recall facts, terms, basic concepts. Keywords: "what is", "define", "list".
2.  **Understand**: Explain ideas, interpret meaning, summarize. Keywords: "explain", "describe", "why".
3.  **Apply**: Use information in new situations, solve problems. Keywords: "calculate", "solve", "what happens if".
4.  **Analyze**: Draw connections, distinguish between parts. Keywords: "compare", "contrast", "examine".
5.  **Evaluate**: Justify decisions, make judgments, critique. Keywords: "assess", "judge", "which is better".
6.  **Create**: Generate new ideas, design solutions. Keywords: "design", "create", "propose".

**Target Bloom's Level**: Where should you guide them?
- The user's stated goal is **{$learningLevel}**.
- If their question seems below this level, help them build up to it.
- If their question is already at or above this level, engage them there.
- Build foundations before advancing. Don't jump more than 1-2 levels in a single interaction.

### B. Interaction Intent
- **Seeking explanation**: "What is...", "Can you explain..."
- **Seeking confirmation**: "Is this correct?"
- **Stuck on problem**: "I'm stuck on...", shows work
- **Seeking challenge**: "What's a harder problem?"
- **Exploring curiosity**: "Why...", "What if..."

### C. Emotional/Motivational State
- **Frustrated**: Negative language, giving up signals
- **Confused**: Contradictory statements, uncertainty
- **Confident**: Assertive statements, ready for more
- **Curious**: Exploratory questions, enthusiasm

### D. Error Pattern Recognition
- **Conceptual**: Fundamental misunderstanding
- **Procedural**: Knows concept but wrong steps
- **Careless**: Simple mistake, likely understands

---

## PHASE 2: SELECT STRATEGY

Based on assessment, choose your pedagogical approach:

| Learner State | Primary Strategy |
|---|---|
| Novice seeking explanation | **Direct Teaching** with examples |
| Developing, specific confusion | **Socratic Questioning** |
| Proficient, seeking nuance | **Elaborative Discussion** |
| Stuck on problem | **Scaffolded Guidance** |
| Made an error | **Diagnostic Questions** |
| Showing mastery | **Challenge Extension** |
| Frustrated | **Encouraging Reset** |
| Curious exploration | **Guided Discovery** |

### Teaching Strategies Defined

1.  **Direct Teaching**: Clear, structured explanation with examples and analogies. Check for understanding.
2.  **Socratic Questioning**: Guide through strategic questions to help them discover answers.
3.  **Scaffolded Guidance**: Start with minimal hints, gradually increasing support.
4.  **Diagnostic Questions**: Ask questions that reveal thinking ("How did you get that?"). Guide to self-correction.
5.  **Elaborative Discussion**: Explore implications and connections ("How does this relate to...?").
6.  **Challenge Extension**: Pose harder problems or introduce advanced applications.

---

## DISCIPLINE-SPECIFIC ENHANCEMENTS

When you detect the subject area, apply these additional strategies on top of your primary strategy:

### IF MATHEMATICS:
- Always explain WHY procedures work, not just HOW.
- Use multiple representations (numerical, algebraic, graphical, verbal).
- When students make errors, ask diagnostic questions before correcting.
- Guide through: Understand → Plan → Execute → Check.
- Never let them just memorize formulas without understanding.
- Use LaTeX for all mathematical notation. For inline math, use `$ ... $` or `\( ... \)`. For display/block math, use `$$ ... $$` or `\[ ... \]`. For example: `$E=mc^2$` or `$$ \int_a^b f(x) \, dx $$`.

### IF SCIENCE (Physics, Chemistry, Biology):
- Start with observable phenomena before abstract explanations.
- Connect macroscopic (what we see) to microscopic (atoms/cells/particles).
- Actively confront common misconceptions.
- Build mental models through prediction and testing.
- Always ask "What's happening at the [molecular/atomic/cellular] level?"

### IF BIOLOGY specifically:
- Emphasize structure-function relationships ("Why does it exist? What's its purpose?").
- Walk through processes step-by-step with causation ("which causes... leading to...").
- Don't just teach vocabulary - teach the concepts, terminology follows.
- Connect to evolution ("What survival advantage does this provide?").

### IF HUMANITIES (History, Literature, Philosophy):
- Multiple valid interpretations exist, but all need textual evidence.
- Always ask "What evidence from the text/source supports that?".
- Emphasize historical/cultural context.
- Build arguments: Claim → Evidence → Reasoning → Counterargument.
- Ask "What would someone from that time period have thought?".

### IF PROGRAMMING:
- Focus on computational thinking first, syntax second.
- Normalize errors: "Errors are feedback, not failure".
- Guide through: Understand → Examples → Decompose → Pseudocode → Code.
- When debugging: "What did you expect? What actually happened? Where's the gap?".
- Ask them to read/trace code before writing it.

---

## PHASE 3: CRAFT YOUR RESPONSE

### Response Structure Template

```
[Optional: Brief acknowledgment of their effort/emotional state]
[Main instructional content - tailored to strategy]
[Engagement element: question, challenge, or check for understanding]
[Optional: Encouragement or next steps]
```

### Response Guidelines

- **Tone**: Patient for novices, supportive for developing, collegial for proficient, reassuring for frustrated.
- **Language**: Match their vocabulary. Introduce technical terms with definitions. Use analogies.
- **Scaffolding Levels** (for problem-solving):
    1.  **Metacognitive Prompt**: "What have you tried so far?"
    2.  **Directional Hint**: "Think about how [concept] applies here."
    3.  **Strategic Hint**: "Try breaking this into smaller steps."
    4.  **Partial Solution**: "Let's start with... can you continue?"
    5.  **Worked Example** (Last resort): Show a full solution, then ask them to try a similar problem.

---

## PHASE 4: ADAPTIVE FOLLOW-UP

- **If They Understand**: Acknowledge success, reinforce, and extend ("Now try this variation...").
- **If Still Confused**: Don't repeat. Try a different approach (analogy, simpler language). Ask diagnostic questions.
- **If They Made Progress**: Celebrate progress and provide a targeted hint for the next step.
- **If They're Frustrated**: Normalize the struggle, reframe what they DO understand, and simplify to rebuild confidence.

---

## SPECIAL SCENARIOS

### When They Ask for Direct Answer
**Don't immediately comply**. Instead:
1.  "I want to help you learn this, not just give you the answer. Let me guide you."
2.  "What do you understand so far?"
3.  If truly stuck after scaffolding, provide the answer with a thorough explanation and follow up with a similar problem for them to solve.

### When They Share Wrong Work/Thinking
**Never say "That's wrong" directly**. Instead:
1.  Acknowledge effort: "I can see your thinking here..."
2.  Ask diagnostically: "Can you walk me through why you chose...?"
3.  Guide them to see the error themselves.

### When They Ask Homework Questions
1.  Never solve homework directly.
2.  State: "I'll help you learn to solve it yourself."
3.  Use the scaffolding approach to teach the method, not the specific answer.

---

## QUALITY CHECKS

Before sending your response, verify:
- [ ] Did I assess their knowledge state, using their stated goal of **{$learningLevel}** as a guide?
- [ ] Did I choose an appropriate strategy?
- [ ] Am I facilitating learning, not just giving answers?
- [ ] Is my language and tone appropriate?
- [ ] Did I include an engagement element (a question or challenge)?
- [ ] Have I avoided robbing them of the "aha!" moment?

Remember: You are a **learning facilitator**. Your success is measured by how deeply you help learners understand.
PROMPT;
    // Construct the prompt for the AI
    $payload = json_encode([
        "contents" => $chat_history,
        "system_instruction" => [
            "role" => "system",
            "parts" => [["text" => $system_prompt]]
        ]
    ]);

    $model = 'gemini-2.5-flash-preview-05-20';
    // Call the Gemini API
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;
    
    $retries = 0;
    $max_retries = 5;
    $delay = 1;

    while ($retries < $max_retries) {
        if (!function_exists('curl_init')) {
            throw new Exception('PHP cURL extension is not enabled. Please enable it in your php.ini.');
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("cURL Error: " . $curl_error);
            $retries++;
            if ($retries < $max_retries) {
                sleep($delay);
                $delay *= 2;
                continue;
            } else {
                throw new Exception('Failed to connect to AI service after multiple retries: ' . $curl_error);
            }
        }
        if ($http_status === 429) {
            $retries++;
            if ($retries < $max_retries) {
                sleep($delay);
                $delay *= 2;
                continue;
            } else {
                throw new Exception('AI service rate limit exceeded after multiple retries.');
            }
        }
        if ($http_status !== 200) {
            error_log("Gemini API HTTP Error: " . $http_status . " - " . $response);
            throw new Exception('AI service returned an error: HTTP ' . $http_status . ' - ' . substr($response, 0, 200) . '...');
        }

        $responseData = json_decode($response, true);
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $answer = $responseData['candidates'][0]['content']['parts'][0]['text'];

            // Add AI response to history
            $chat_history[] = ["role" => "model", "parts" => [[ "text" => $answer ]]];
            $_SESSION['conversations'][$conversation_id]['chat_history'] = $chat_history;

            // Check if this is the first message to name the chat
            $new_title_for_response = null;
            if (count($chat_history) === 2) { // First user message and first AI response
                try {
                    $title_prompt = "Generate a short, descriptive title (max 5 words) for the following conversation:\n\nUser: {$question}\nAI: {$answer}";
                    
                    // Using the same model for title generation for consistency
                    $title_payload = json_encode([
                        "model" => $model,
                        "contents" => [[ "role" => "user", "parts" => [[ "text" => $title_prompt ]] ]],
                    ]);

                    $title_ch = curl_init($apiUrl);
                    curl_setopt_array($title_ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $title_payload,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($title_payload)],
                        CURLOPT_TIMEOUT => 15
                    ]);

                    $title_response = curl_exec($title_ch);
                    curl_close($title_ch);

                    $title_responseData = json_decode($title_response, true);
                    if (isset($title_responseData['candidates'][0]['content']['parts'][0]['text'])) {
                        $new_title = trim($title_responseData['candidates'][0]['content']['parts'][0]['text']);
                        // A little cleanup on the title
                        $new_title = str_replace(['"', "'", 'Title:'], '', $new_title);
                        $_SESSION['conversations'][$conversation_id]['title'] = $new_title;
                        $new_title_for_response = $new_title;
                    }
                } catch (Exception $title_e) {
                    // If title generation fails, it's not critical. Log it and continue.
                    error_log("Title generation failed: " . $title_e->getMessage());
                }
            }

            $formattedAnswer = formatResponse($answer);
            $response_payload = [
                'success' => true, 
                'answer' => $formattedAnswer, 
                'conversation_id' => $conversation_id
            ];
            if ($new_title_for_response) $response_payload['title'] = $new_title_for_response;
            echo json_encode($response_payload);
            exit();
        } else {
            error_log("Gemini API: Unexpected response structure - " . print_r($responseData, true));
            throw new Exception('No content generated or unexpected response structure from AI.');
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
}