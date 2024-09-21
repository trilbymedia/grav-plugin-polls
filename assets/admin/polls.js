// Function to convert a string to a slug
function stringToSlug(str) {
    return str.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
}

function addListenersToItems(item) {
    const questionInput = item.querySelector('input[type="text"][name*="[question]"]');
    const idInput = item.querySelector('input[type="text"][name*="[id]"]');
    const formDescription = item.querySelector('.form-extra-wrapper .form-description code');

    if (!questionInput || !idInput || !formDescription) return;

    let isIdEdited = idInput.value.trim() !== '';

    // Function to update the embed code
    function updateEmbedCode(newId) {
        formDescription.textContent = `[poll id="${newId}" /]`;
    }

    // Update the embed code on page load
    updateEmbedCode(idInput.value || stringToSlug(questionInput.value));

    // Update idInput when questionInput changes
    questionInput.addEventListener('input', function() {
        if (!isIdEdited) {
            const slugifiedId = stringToSlug(questionInput.value);
            idInput.value = slugifiedId;
            updateEmbedCode(slugifiedId); // Update the embed code
        }
    });

    // Track if idInput is manually edited
    idInput.addEventListener('input', function() {
        const newIdValue = idInput.value.trim();
        if (newIdValue === '') {
            isIdEdited = false;
        } else {
            isIdEdited = true;
            updateEmbedCode(newIdValue); // Update the embed code
        }
    });

    const maxAnswersInput = item.querySelector('input[type="number"][name*="[max_answers]"]');
    const minAnswersInput = item.querySelector('input[type="number"][name*="[min_answers]"]');
    let isMinAnswersEdited = minAnswersInput.value.trim() !== maxAnswersInput.value.trim();

    maxAnswersInput.addEventListener('input', function() {
        if (!isMinAnswersEdited) {
            minAnswersInput.value = maxAnswersInput.value;
        }
    });

    minAnswersInput.addEventListener('input', function() {
        isMinAnswersEdited = true;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const list = document.querySelector('ul[data-collection-holder]');
    list.querySelectorAll('li[data-collection-item]').forEach(addListenersToItems);

    // Observer to watch for new li elements
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE && node.matches('li[data-collection-item]')) {
                    addListenersToItems(node);
                }
            });
        });
    });

    // Configuration of the observer:
    const config = { childList: true };
    observer.observe(list, config);
});