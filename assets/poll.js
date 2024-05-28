document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.poll__options');

    containers.forEach(container => {
        const maxChoices = parseInt(container.getAttribute('data-max-answers'));
        const minChoices = parseInt(container.getAttribute('data-min-answers'));
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        const form = container.closest('.poll__container').querySelector('.pollform');
        const submitButton = form.querySelector('.poll__submit');
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
                        alert('Error: ' + json.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });
});