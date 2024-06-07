document.addEventListener('DOMContentLoaded', function() {
    const updateSubmitButtonState = (submitButton, checkedQueue, minChoices) => {
        submitButton.disabled = checkedQueue.length < minChoices;
        // console.log(`Submit button state updated: ${submitButton.disabled}, checked count: ${checkedQueue.length}, minChoices: ${minChoices}`);
    };

    const handleCheckboxChange = (checkbox, checkedQueue, maxChoices, submitButton, minChoices) => {
        if (checkbox.checked) {
            checkedQueue.push(checkbox);
            if (checkedQueue.length > maxChoices) {
                checkedQueue.shift().checked = false;
            }
        } else {
            checkedQueue = checkedQueue.filter(chk => chk !== checkbox);
        }
        updateSubmitButtonState(submitButton, checkedQueue, minChoices);
    };

    const showError = (pollContainer, message) => {
        const errorsDiv = pollContainer.querySelector('[data-messages] .poll__errors') || pollContainer.querySelector('[data-messages]').appendChild(document.createElement('div'));
        errorsDiv.className = 'poll__errors';
        errorsDiv.innerHTML = `<p>${message}</p>`;
    };

    const fetchData = (url, method, body, pollContainer, callback) => {
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : null
        })
        .then(response => response.json())
        .then(json => {
            if (json.code === 200) {
                pollContainer.outerHTML = json.content;
                // console.log('Content replaced with:', json.content);
                const newPollContainer = document.querySelector(`[data-poll-id="${body ? body.id : pollContainer.getAttribute('data-poll-id')}"]`);
                if (newPollContainer) {
                    initializeListeners(newPollContainer);
                }
                if (callback) callback();
            } else {
                showError(pollContainer, json.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    const handleFormSubmit = (event, form, pollContainer, checkedQueue, minChoices, maxChoices) => {
        event.preventDefault();

        let selectedOptions = [];
        form.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => selectedOptions.push(checkbox.value));

        const data = {
            id: form.querySelector('input[name="id"]').value,
            nonce: form.querySelector('input[name="nonce"]').value,
            answers: selectedOptions
        };

        // console.log('Submitting data:', data);

        fetchData(pollContainer.getAttribute('data-callback') + '.json', 'POST', data, pollContainer);
    };

    const initializePollFormListeners = (pollContainer) => {
        console.log('Initializing poll form listeners for:', pollContainer);
        const form = pollContainer.querySelector('.pollform');
        if (!form) return;

        const submitButton = form.querySelector('.poll__submit');
        const pollOptions = pollContainer.querySelector('.poll__options');
        const maxChoices = +pollOptions.getAttribute('data-max-answers');
        const minChoices = +pollOptions.getAttribute('data-min-answers');
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        let checkedQueue = Array.from(checkboxes).filter(checkbox => checkbox.checked);

        updateSubmitButtonState(submitButton, checkedQueue, minChoices);

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => handleCheckboxChange(checkbox, checkedQueue, maxChoices, submitButton, minChoices));
        });

        form.addEventListener('submit', event => handleFormSubmit(event, form, pollContainer, checkedQueue, minChoices, maxChoices));
    };

    const handleViewButtonClick = (view, pollContainer) => {
        // console.log(`Handling view button click for view: ${view}`);
        const id = pollContainer.getAttribute('data-poll-id');
        const url = pollContainer.getAttribute('data-callback') + `.json?view=${view}&id=${id}`;
        fetchData(url, 'GET', null, pollContainer);
    };

    const initializeViewButtonsListeners = (pollContainer) => {
        // console.log('Initializing view buttons listeners for:', pollContainer);
        const viewResultsButton = pollContainer.querySelector('.poll__view[data-view="results"]');
        const backToVoteButton = pollContainer.querySelector('.poll__view[data-view="poll"]');

        viewResultsButton && viewResultsButton.addEventListener('click', () => handleViewButtonClick('results', pollContainer));
        backToVoteButton && backToVoteButton.addEventListener('click', () => handleViewButtonClick('poll', pollContainer));
    };

    const initializeListeners = (pollContainer) => {
        // console.log('Initializing listeners for:', pollContainer);
        initializePollFormListeners(pollContainer);
        initializeViewButtonsListeners(pollContainer);
    };

    document.querySelectorAll('.poll__container').forEach(initializeListeners);
});