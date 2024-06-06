document.addEventListener('DOMContentLoaded', function() {
    // Function to initialize event listeners for the poll form
    const initializePollFormListeners = (pollContainer) => {
        console.log('Initializing poll form listeners for:', pollContainer);
        const form = pollContainer.querySelector('.pollform');
        if (!form) return;

        const submitButton = form.querySelector('.poll__submit');
        const pollOptions = pollContainer.querySelector('.poll__options');
        const maxChoices = parseInt(pollOptions.getAttribute('data-max-answers'), 10);
        const minChoices = parseInt(pollOptions.getAttribute('data-min-answers'), 10);
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        let checkedQueue = Array.from(checkboxes).filter(checkbox => checkbox.checked);

        const updateSubmitButtonState = () => {
            if (submitButton) {
                submitButton.disabled = checkedQueue.length < minChoices;
                console.log(`Submit button state updated: ${submitButton.disabled}, checked count: ${checkedQueue.length}, minChoices: ${minChoices}`);
            }
        };

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    checkedQueue.push(this);
                    if (checkedQueue.length > maxChoices) {
                        const checkboxToUncheck = checkedQueue.shift();
                        checkboxToUncheck.checked = false;
                    }
                } else {
                    checkedQueue = checkedQueue.filter(chk => chk !== this);
                }
                updateSubmitButtonState();
            });
        });

        updateSubmitButtonState();

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            let selectedOptions = [];
            form.querySelectorAll('input[type="checkbox"]:checked').forEach(function(checkbox) {
                selectedOptions.push(checkbox.value);
            });

            if (selectedOptions.length < minChoices || selectedOptions.length > maxChoices) {
                let errorsDiv = pollContainer.querySelector('[data-messages] .poll__errors');
                if (!errorsDiv) {
                    errorsDiv = document.createElement('div');
                    errorsDiv.className = 'poll__errors';
                    pollContainer.querySelector('[data-messages]').appendChild(errorsDiv);
                }
                errorsDiv.innerHTML = `<p>Error: Please select between ${minChoices} and ${maxChoices} options.</p>`;
                return; // Prevent form submission if conditions are not met
            }

            let data = {
                id: form.querySelector('input[name="id"]').value,
                nonce: form.querySelector('input[name="nonce"]').value,
                answers: selectedOptions
            };

            let callbackUrl = pollContainer.getAttribute('data-callback') + '.json';

            fetch(callbackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(json => {
                if (json.code === 200) {
                    pollContainer.outerHTML = json.content;
                    console.log('Content replaced with:', json.content);
                    // Re-initialize event listeners for the new content
                    const newPollContainer = document.querySelector(`[data-poll-id="${data.id}"]`);
                    if (newPollContainer) {
                        initializeListeners(newPollContainer);
                    }
                } else {
                    let errorsDiv = pollContainer.querySelector('[data-messages] .poll__errors');
                    if (!errorsDiv) {
                        errorsDiv = document.createElement('div');
                        errorsDiv.className = 'poll__errors';
                        pollContainer.querySelector('[data-messages]').appendChild(errorsDiv);
                    }
                    errorsDiv.innerHTML = '<p>Error: ' + json.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    };

    // Function to initialize event listeners for view buttons
    const initializeViewButtonsListeners = (pollContainer) => {
        console.log('Initializing view buttons listeners for:', pollContainer);
        const viewResultsButton = pollContainer.querySelector('.poll__view[data-view="results"]');
        const backToVoteButton = pollContainer.querySelector('.poll__view[data-view="poll"]');

        console.log('View buttons found:', { viewResultsButton, backToVoteButton });

        const handleViewButtonClick = (view) => {
            console.log(`Handling view button click for view: ${view}`);
            let id = pollContainer.getAttribute('data-poll-id');
            let callbackUrl = pollContainer.getAttribute('data-callback') + '.json?view=' + view + '&id=' + id;

            fetch(callbackUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(json => {
                if (json.code === 200) {
                    pollContainer.outerHTML = json.content;
                    console.log('Content replaced with:', json.content);
                    // Re-initialize event listeners for the new content
                    const newPollContainer = document.querySelector(`[data-poll-id="${id}"]`);
                    if (newPollContainer) {
                        initializeListeners(newPollContainer);
                    }
                } else {
                    let errorsDiv = pollContainer.querySelector('[data-messages] .poll__errors');
                    if (!errorsDiv) {
                        errorsDiv = document.createElement('div');
                        errorsDiv.className = 'poll__errors';
                        pollContainer.querySelector('[data-messages]').appendChild(errorsDiv);
                    }
                    errorsDiv.innerHTML = '<p>Error: ' + json.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        };

        if (viewResultsButton) {
            viewResultsButton.addEventListener('click', function() {
                console.log('View results button clicked');
                handleViewButtonClick('results');
            });
        }

        if (backToVoteButton) {
            backToVoteButton.addEventListener('click', function() {
                console.log('Back to vote button clicked');
                handleViewButtonClick('poll');
            });
        }
    };

    // Function to initialize all necessary listeners
    const initializeListeners = (pollContainer) => {
        console.log('Initializing listeners for:', pollContainer);
        initializePollFormListeners(pollContainer);
        initializeViewButtonsListeners(pollContainer);
    };

    // Initialize event listeners for all existing containers on page load
    document.querySelectorAll('.poll__container').forEach(pollContainer => {
        initializeListeners(pollContainer);
    });
});