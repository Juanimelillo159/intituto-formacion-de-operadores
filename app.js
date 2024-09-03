document.addEventListener('DOMContentLoaded', function() {
    function loadCourses(page = 1, search = '') {
        const formData = new FormData();
        formData.append('page', page);
        formData.append('search', search);

        fetch('cursos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            document.querySelector('#cursos-list').innerHTML = data;
            addPaginationEventListeners(); // Volver a añadir los eventos a los nuevos enlaces de paginación
        })
        .catch(error => console.error('Error:', error));
    }

    function addPaginationEventListeners() {
        document.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                const search = document.querySelector('#search-input').value;
                loadCourses(page, search);
            });
        });
    }

    document.querySelector('#search-input').addEventListener('input', function() {
        loadCourses(1, this.value);
    });

    loadCourses(); // Cargar cursos inicialmente
});
