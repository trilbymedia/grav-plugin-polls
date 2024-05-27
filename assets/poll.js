document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.poll__options');

    containers.forEach(container => {
        const maxChoices = parseInt(container.getAttribute('data-max-answers'));
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        let checkedQueue = [];

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
            });
        });
    });
});