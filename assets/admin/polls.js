// Function to convert a string to a slug
function stringToSlug(str) {
    return str.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
}

// Function to add event listeners to a single item
function addListenersToItems(item) {
    const questionInput = item.querySelector('input[type="text"][name*="[question]"]');
    const idInput = item.querySelector('input[type="text"][name*="[id]"]');
    if (!questionInput || !idInput) return;
    let isIdEdited = idInput.value.trim() !== '';

    questionInput.addEventListener('input', function() {
        if (!isIdEdited) {
            idInput.value = stringToSlug(questionInput.value);
        }
    });

    idInput.addEventListener('input', function() {
        if (idInput.value.trim() === '') {
            isIdEdited = false;
        } else {
            isIdEdited = true;
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
            console.log('mutation');
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