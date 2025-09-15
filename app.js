document.addEventListener('DOMContentLoaded', function() {
    function loadCourses() {
        const formData = new FormData();
        fetch('cursos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            document.querySelector('#cursos-list').innerHTML = data;
        })
        .catch(error => console.error('Error:', error));
    }


    loadCourses(); // Cargar cursos inicialmente
});
