$(document).ready(function() {

    $('#pedidos').DataTable({

        'paging'      : true,
        'lengthChange': false,
        'searching'   : true,
        'ordering'    : true,
        'info'        : true,
        'autoWidth'   : false,
        'pageLength'  : 7,
        'language': {
            paginate: {
              next: 'Siguiente',
              previous: 'Anterior' 
              
            },
            info: "Mostrando _START_ a _END_ de _TOTAL_ resultados",
            search: 'Buscar', 
            emptyTable: 'Aun no se registro ningun pedido',
            infoEmpty: 'Mostrando 0 a 0 de 0 entradas' 
          }
    });

    $('#gastos').DataTable({

      'paging'      : true,
      'lengthChange': false,
      'searching'   : true,
      'ordering'    : true,
      'info'        : true,
      'autoWidth'   : false,
      'pageLength'  : 7,
      'language': {
          paginate: {
            next: 'Siguiente', 
            previous: 'Anterior'  
            
          },
          info: "Mostrando _START_ a _END_ de _TOTAL_ resultados",
          search: 'Buscar', 
          emptyTable: 'Aun no hay pagos registrados',
          infoEmpty: 'Mostrando 0 a 0 de 0 entradas' 
        }
  });

  $('#registros').DataTable({

    'paging'      : true,
    'lengthChange': false,
    'searching'   : false,
    'ordering'    : true,
    'info'        : true,
    'autoWidth'   : false,
    'pageLength'  : 7,
    'language': {
        paginate: {
          next: 'Siguiente', 
          previous: 'Anterior'  
          
        },
        info: "Mostrando _START_ a _END_ de _TOTAL_ resultados",
        emptyTable: 'Aun no se encuentran datos registrados',
        infoEmpty: 'Mostrando 0 a 0 de 0 entradas' 
      }
});
$('#domicilio').DataTable({

  'paging'      : true,
  'lengthChange': false,
  'searching'   : false,
  'ordering'    : true,
  'info'        : true,
  'autoWidth'   : false,
  'pageLength'  : 7,
  'language': {
      paginate: {
        next: 'Siguiente', 
        previous: 'Anterior'  
        
      },
      info: "Mostrando _START_ a _END_ de _TOTAL_ resultados",
      emptyTable: 'No hay registros de domicilios',
      infoEmpty: 'Mostrando 0 a 0 de 0 entradas' 
    }
});

    
  });

