<?php
// A unique ID for the editor instance, to allow multiple editors on the same page.
$editor_id = 'wysiwyg_editor_' . uniqid();
$textarea_name = isset($textarea_name) ? $textarea_name : 'editor_content';
$initial_content = isset($initial_content) ? $initial_content : '';
?>

<style>
    /* Basic Editor Styles */
    .wysiwyg-editor-container {
        border: 1px solid var(--border-1, #59524c46);
        border-radius: 8px;
        overflow: hidden;
        background-color: var(--card-bg-2, #22201E);
        color: var(--lighter-grey, #b3b3b3);
        font-family: var(--font, "Roboto", serif);
    }

    .wysiwyg-toolbar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background-color: var(--card-bg, #1D1A18);
        border-bottom: 1px solid var(--border-1, #59524c46);
    }

    .wysiwyg-toolbar button,
    .wysiwyg-toolbar select {
        background: transparent;
        border: 1px solid transparent;
        color: var(--lighter-grey, #b3b3b3);
        font-size: 1rem;
        padding: 0.35rem 0.5rem;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .wysiwyg-toolbar select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-color: var(--card-bg-3, #1A1C1D);
        padding-right: 1.5rem; /* Space for arrow */
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23b3b3b3' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.5rem center;
        background-size: 1em;
    }
    
    .wysiwyg-toolbar button:hover,
    .wysiwyg-toolbar select:hover,
    .wysiwyg-toolbar button.is-active {
        background-color: var(--gold, #b8845c);
        color: var(--body-bg, #161412);
        border-color: var(--gold, #b8845c);
    }
    
    .wysiwyg-toolbar button i {
        pointer-events: none;
    }

    .wysiwyg-content {
        min-height: 250px;
        padding: 1rem;
        outline: none;
        overflow-y: auto;
    }
    
    .wysiwyg-content:focus {
        border-color: var(--gold, #b8845c);
    }
    
    .wysiwyg-content iframe {
        max-width: 100%;
        height: auto;
        aspect-ratio: 16/9;
    }

    .wysiwyg-content img {
        max-width: 100%;
        height: auto;
        display: block;
        border-radius: 8px; /* Consistent with other elements */
    }

    /* New Color Picker Styles */
    .wysiwyg-color-picker-container {
        position: relative;
        display: inline-block;
    }

    .wysiwyg-color-palette {
        display: none; /* Hidden by default */
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        background-color: var(--card-bg, #1D1A18);
        border: 1px solid var(--border-1, #59524c46);
        border-radius: 8px;
        padding: 0.75rem;
        z-index: 10;
        grid-template-columns: repeat(6, 1fr);
        gap: 0.5rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }

    .wysiwyg-color-palette.is-open {
        display: grid; /* Becomes a grid when open */
    }

    .wysiwyg-color-swatch {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid var(--card-bg, #1D1A18);
        transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .wysiwyg-color-swatch:hover {
        transform: scale(1.15);
        border-color: var(--gold, #b8845c);
    }

</style>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<div id="<?php echo $editor_id; ?>" class="wysiwyg-editor-container">
    <div class="wysiwyg-toolbar">
        <button type="button" data-command="bold" title="Bold"><i class="fas fa-bold"></i></button>
        <button type="button" data-command="italic" title="Italic"><i class="fas fa-italic"></i></button>
        <button type="button" data-command="underline" title="Underline"><i class="fas fa-underline"></i></button>
        <button type="button" data-command="insertUnorderedList" title="Bullet List"><i class="fas fa-list-ul"></i></button>
        <button type="button" data-command="insertOrderedList" title="Numbered List"><i class="fas fa-list-ol"></i></button>
        <button type="button" data-command="justifyCenter" title="Center Align"><i class="fas fa-align-center"></i></button>
        <button type="button" data-command="createLink" title="Add Link"><i class="fas fa-link"></i></button>
        <button type="button" data-command="insertImage" title="Insert Image"><i class="fas fa-image"></i></button>
        <button type="button" data-command="embedYouTube" title="Embed YouTube Video"><i class="fab fa-youtube"></i></button>
        
       

        <!-- New Visual Color Picker -->
        <div class="wysiwyg-color-picker-container">
            <button type="button" class="wysiwyg-color-picker-trigger" title="Text Color">
                <i class="fas fa-palette"></i>
            </button>
            <div class="wysiwyg-color-palette">
                <!-- Row 1: Project & Greys -->
                <span class="wysiwyg-color-swatch" data-color="#C69653" style="background-color:#C69653;" title="Light Gold"></span>
                <span class="wysiwyg-color-swatch" data-color="#b8845c" style="background-color:#b8845c;" title="Gold"></span>
                <span class="wysiwyg-color-swatch" data-color="#3DA6A2" style="background-color:#3DA6A2;" title="Turquoise"></span>
                <span class="wysiwyg-color-swatch" data-color="#ffffff" style="background-color:#ffffff;" title="White"></span>
                <span class="wysiwyg-color-swatch" data-color="#b3b3b3" style="background-color:#b3b3b3;" title="Lighter Grey"></span>
                <span class="wysiwyg-color-swatch" data-color="#808080" style="background-color:#808080;" title="Grey"></span>
                <!-- Row 2: Standard Colors -->
                <span class="wysiwyg-color-swatch" data-color="#EB0000" style="background-color:#EB0000;" title="Red"></span>
                <span class="wysiwyg-color-swatch" data-color="#fd7e14" style="background-color:#fd7e14;" title="Orange"></span>
                <span class="wysiwyg-color-swatch" data-color="#FCDC00" style="background-color:#FCDC00;" title="Yellow"></span>
                <span class="wysiwyg-color-swatch" data-color="#28a745" style="background-color:#28a745;" title="Green"></span>
                <span class="wysiwyg-color-swatch" data-color="#007BFF" style="background-color:#007BFF;" title="Blue"></span>
                <span class="wysiwyg-color-swatch" data-color="#8A2BE2" style="background-color:#8A2BE2;" title="Purple"></span>
            </div>
        </div>
         <!-- Font Size Selector -->
         <select data-command="formatBlock" title="Font Size">
            <option value="h1">Largest Heading</option>
            <option value="h2">Large Heading</option>
            <option value="h3">Medium Heading</option>
            <option value="h4">Small Heading</option>
            <option value="h5">Smaller Heading</option>
            <option value="h6">Smallest Heading</option>
            <option value="p" selected>Normal</option>
        </select>
    </div>

    <div class="wysiwyg-content" contenteditable="true" spellcheck="false"><?php echo htmlspecialchars_decode($initial_content); ?></div>

    <textarea name="<?php echo $textarea_name; ?>" style="display:none;"></textarea>
</div>

<script>
function initializeWysiwygEditor(editorContainer) {
    if (!editorContainer) return;

    const toolbar = editorContainer.querySelector('.wysiwyg-toolbar');
    const editor = editorContainer.querySelector('.wysiwyg-content');
    const hiddenTextarea = editorContainer.querySelector('textarea');
    const colorPickerContainer = editorContainer.querySelector('.wysiwyg-color-picker-container');
    const colorTrigger = colorPickerContainer.querySelector('.wysiwyg-color-picker-trigger');
    const colorPalette = colorPickerContainer.querySelector('.wysiwyg-color-palette');

    // Handle dropdown commands
    const selects = editorContainer.querySelectorAll('.wysiwyg-toolbar select');
    selects.forEach(select => {
        select.addEventListener('change', (e) => {
            const command = e.target.dataset.command;
            const value = e.target.value;
            if (command && value) {
                document.execCommand(command, false, value);
            }
            editor.focus();
        });
    });

    // Handle new color picker
    colorTrigger.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent document click listener from closing it immediately
        colorPalette.classList.toggle('is-open');
    });

    // CRITICAL FIX: Prevent the color palette from stealing focus when clicked.
    // This keeps the text selection in the editor active.
    colorPalette.addEventListener('mousedown', (e) => {
        e.preventDefault();
    });

    colorPalette.addEventListener('click', (e) => {
        if (e.target.classList.contains('wysiwyg-color-swatch')) {
            const color = e.target.dataset.color;
            document.execCommand('foreColor', false, color);
            colorPalette.classList.remove('is-open');
            editor.focus();
        }
    });

    // Close color picker when clicking outside
    document.addEventListener('click', (e) => {
        if (!colorPickerContainer.contains(e.target) && colorPalette.classList.contains('is-open')) {
            colorPalette.classList.remove('is-open');
        }
    });

    // Set initial content for textarea
    hiddenTextarea.value = editor.innerHTML;

    // Sync editor content to hidden textarea on input
    editor.addEventListener('input', () => {
        hiddenTextarea.value = editor.innerHTML;
        updateToolbarState();
    });

    // Handle toolbar commands
    toolbar.addEventListener('click', (e) => {
        // This handler is only for buttons with a data-command attribute.
        const target = e.target.closest('button[data-command]');

        // If the click wasn't on a command button, ignore it.
        // This prevents interference with custom buttons like the color picker.
        if (!target) {
            return;
        }

        e.preventDefault();
        
        const command = target.dataset.command;

        if (command === 'createLink') {
            const url = prompt('Enter the URL:');
            if (url) {
                document.execCommand(command, false, url);
            }
        } else if (command === 'insertImage') {
            const url = prompt('Enter the Image URL:');
            if (url) {
                document.execCommand(command, false, url);
            }
        } else if (command === 'embedYouTube') {
            const videoUrl = prompt('Enter YouTube Video URL:');
            if (videoUrl) {
                const videoId = getYouTubeVideoId(videoUrl);
                if (videoId) {
                    const iframe = document.createElement('iframe');
                    iframe.src = `https://www.youtube.com/embed/${videoId}`;
                    iframe.width = '560';
                    iframe.height = '315';
                    iframe.title = 'YouTube video player';
                    iframe.frameborder = '0';
                    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                    iframe.allowFullscreen = true;
                    
                    // Insert at cursor position
                    const selection = window.getSelection();
                    if (selection.getRangeAt && selection.rangeCount) {
                        const range = selection.getRangeAt(0);
                        range.deleteContents();
                        range.insertNode(iframe);
                    } else {
                         editor.appendChild(iframe);
                    }
                } else {
                    alert('Invalid YouTube URL.');
                }
            }
        } else {
            document.execCommand(command, false, null);
        }
        
        editor.focus();
        updateToolbarState();
    });

    // Update toolbar button states based on selection
    const updateToolbarState = () => {
        const buttons = toolbar.querySelectorAll('button[data-command]');
        buttons.forEach(button => {
            const command = button.dataset.command;
            if (command && command !== 'embedYouTube' && command !== 'createLink' && command !== 'insertImage') {
                 try {
                    if (document.queryCommandState(command)) {
                        button.classList.add('is-active');
                    } else {
                        button.classList.remove('is-active');
                    }
                } catch (e) {
                    // This can fail on non-standard commands, just ignore.
                }
            }
        });

        // Update formatBlock (font size) selector
        try {
            const formatBlockSelect = toolbar.querySelector('select[data-command="formatBlock"]');
            if (formatBlockSelect) {
                const blockValue = document.queryCommandValue('formatBlock').toLowerCase();
                // Normalize 'div' or other tags for normal text to 'p'
                const currentValue = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(blockValue) ? blockValue : 'p';
                formatBlockSelect.value = currentValue;
            }
        } catch (e) {
            // Ignore errors, command might not be supported in all contexts
        }
    };
    
    editor.addEventListener('keyup', updateToolbarState);
    editor.addEventListener('mouseup', updateToolbarState);
    editor.addEventListener('focus', updateToolbarState);
    editor.addEventListener('click', updateToolbarState);


    // Helper function to extract YouTube video ID from URL
    function getYouTubeVideoId(url) {
        let ID = '';
        url = url.replace(/(>|<)/gi, '').split(/(vi\/|v=|\/v\/|youtu\.be\/|\/embed\/)/);
        if (url[2] !== undefined) {
            ID = url[2].split(/[^0-9a-z_\-]/i);
            ID = ID[0];
        } else {
            ID = url;
        }
        return ID;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const mainEditor = document.getElementById('<?php echo $editor_id; ?>');
    if (mainEditor) {
        initializeWysiwygEditor(mainEditor);
    }
});
</script> 