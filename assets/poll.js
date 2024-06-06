document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.poll__options');

    containers.forEach(container => {
        const maxChoices = parseInt(container.getAttribute('data-max-answers'));
        const minChoices = parseInt(container.getAttribute('data-min-answers'));
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        const pollContainer = container.closest('.poll__container');
        const form = pollContainer.querySelector('.pollform');
        const submitButton = form.querySelector('.poll__submit');
        const viewButton = form.querySelector('.poll__view');
        let checkedQueue = [];

        const updateSubmitButtonState = () => {
            submitButton.disabled = checkedQueue.length < minChoices;
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

        viewButton.addEventListener('click', function() {
            let id = pollContainer.getAttribute('data-poll-id');
            let view = viewButton.getAttribute('data-view');
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
                    pollContainer.innerHTML = json.content;
                } else {
                    let errorsDiv = pollContainer.querySelector('[data-messages] .poll__errors');
                    if (!errorsDiv) {
                        errorsDiv = document.createElement('div');
                        errorsDiv.className = 'poll__errors';
                        pollContainer.querySelector('[data-messages]').appendChild(errorsDiv);
                    }
                    errorsDiv.textContent = '<p>Error: ' + json.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });

    document.querySelectorAll('.poll__container').forEach(function(container) {
            container.querySelector('.pollform').addEventListener('submit', function(event) {
                event.preventDefault();

                let form = event.target;
                let selectedOptions = [];

                form.querySelectorAll('input[type="checkbox"]:checked').forEach(function(checkbox) {
                    selectedOptions.push(checkbox.value);
                });

                let data = {
                    id: form.querySelectorAll('input[name="id"]')[0].value,
                    nonce: form.querySelectorAll('input[name="nonce"]')[0].value,
                    answers: selectedOptions
                };

                let callbackUrl = container.getAttribute('data-callback') + '.json';

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
                        container.innerHTML = json.content;
                    } else {
                        let errorsDiv = container.querySelector('[data-messages] .poll__errors');
                        if (!errorsDiv) {
                            errorsDiv = document.createElement('div');
                            errorsDiv.className = 'poll__errors';
                            container.querySelector('[data-messages]').appendChild(errorsDiv);
                        }
                        errorsDiv.innerHTML = '<p>Error: ' + json.message + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });
});