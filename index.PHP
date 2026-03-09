<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja de Ahorro - Acceso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="shortcut icon" href=".//logo/capcel.png">
    <style>
        :root {
            --primary-color: #0056b3;
            --secondary-color: #007bff;
            --background-color: #f0f2f5;
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 0.75rem;
        }

        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--background-color);
            background-image: linear-gradient(135deg, #e9f0f8 0%, #d6e4f7 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            max-width: 420px;
            width: 100%;
        }

        .login-card {
            background-color: #ffffff;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            animation: fadeIn 0.8s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            background-color: var(--primary-color);
            padding: 2rem;
            text-align: center;
        }

        .login-header .logo {
            max-width: 150px;
            margin-bottom: 1rem;
        }

        .login-header h2 {
            color: #ffffff;
            font-weight: 600;
        }

        .login-body {
            padding: 2.5rem;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }

        .input-group-text {
            background-color: #e9ecef;
            border-right: none;
        }

        .form-control {
            border-left: none;
            height: 48px;
        }

        .btn-login {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem;
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn-login:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .footer-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 1.5rem;
        }

        .register-link {
            cursor: pointer;
            color: var(--primary-color);
            font-weight: 500;
        }

        .register-link:hover {
            text-decoration: underline;
        }

        /* Modal styles */
        .modal-content {
            border-radius: var(--border-radius);
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="login-container p-3">
        <div class="card login-card">
            <div class="login-header">
                <img src="logo/capcel.png" alt="Logo de la Institución" class="logo">
                <h2>Bienvenido</h2>
            </div>
            <div class="card-body login-body">
                <div id="alert-container"></div>
                <form id="loginForm" novalidate>
                    <div class="input-group mb-4">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" name="usuario" placeholder="Correo" required>
                    </div>

                    <div class="input-group mb-4">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" name="clave" placeholder="Contraseña" required>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-login">Ingresar</button>
                    </div>
                    
                    <p class="text-center">¿No tienes cuenta? <span class="register-link" data-bs-toggle="modal" data-bs-target="#registerModal">Regístrate aquí</span></p>
                </form>
                <p class="text-center footer-text" id="footer-year"></p>
            </div>
        </div>
    </div>

    <!-- Modal de Registro -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registro de Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="registerAlert"></div>
                    
                    <!-- Paso 1: Validación de Código de Empleado -->
                    <div id="step1" class="step active">
                        <p>Para registrarte, primero debemos verificar que estás en nuestra base de datos de socios.</p>
                        <div class="mb-3">
                            <label for="codigo_empleado" class="form-label">Código de Empleado</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="codigo_empleado" placeholder="Ingresa tu código de empleado" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paso 2: Formulario de Registro -->
                    <div id="step2" class="step">
                        <form id="registerForm" novalidate>
                            <input type="hidden" id="codigo_validado">
                            
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="nombre" required readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="usuario" class="form-label">Nombre de Usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <input type="text" class="form-control" id="usuario" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" required minlength="6">
                                </div>
                                <div class="form-text">Mínimo 6 caracteres</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirmar Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirmPassword" required>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="prevBtn" class="btn btn-secondary" style="display: none;">Atrás</button>
                    <button type="button" id="nextBtn" class="btn btn-primary">Verificar Código</button>
                    <button type="button" id="submitBtn" class="btn btn-primary" style="display: none;">Registrarse</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // Establecer el año actual
            $('#footer-year').text(`© ${new Date().getFullYear()} Caja de Ahorro. Todos los derechos reservados.`);

            // Login Form
            $('#loginForm').on('submit', function(event) {
                event.preventDefault();
                
                if (this.checkValidity() === false) {
                    event.stopPropagation();
                    $(this).addClass('was-validated');
                    return;
                }

                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verificando...');

                $.ajax({
                    url: 'auth.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.redirect;
                        } else {
                            $('#alert-container').html(`
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    ${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `);
                        }
                    },
                    error: function() {
                        $('#alert-container').html(`
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error al conectar con el servidor. Intente nuevamente.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `);
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html('Ingresar');
                    }
                });
            });

            // Registro Modal Logic
            let currentStep = 1;
            const totalSteps = 2;
            
            function showStep(step) {
                $('.step').removeClass('active');
                $(`#step${step}`).addClass('active');
                
                if (step === 1) {
                    $('#prevBtn').hide();
                    $('#nextBtn').show().text('Verificar Código');
                    $('#submitBtn').hide();
                    $('#registerAlert').empty();
                } else if (step === 2) {
                    $('#prevBtn').show();
                    $('#nextBtn').hide();
                    $('#submitBtn').show().text('Registrarse');
                }
            }
            
            $('#nextBtn').click(function() {
                if (currentStep === 1) {
                    const codigoEmpleado = $('#codigo_empleado').val().trim();
                    
                    if (!codigoEmpleado) {
                        $('#registerAlert').html(`
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Por favor ingresa tu código de empleado.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `);
                        return;
                    }
                    
                    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verificando...');
                    
                    $.ajax({
                        url: 'check_codigo_empleado.php',
                        type: 'POST',
                        data: { codigo_empleado: codigoEmpleado },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Respuesta del servidor:', response);
                            
                            if (response.error) {
                                $('#registerAlert').html(`
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        Error del servidor: ${response.error}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `);
                                return;
                            }
                            
                            if (response.exists) {
                                $('#codigo_validado').val(codigoEmpleado);
                                $('#nombre').val(response.nombre || '');
                                currentStep++;
                                showStep(currentStep);
                            } else {
                                $('#registerAlert').html(`
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        No se encontró el código de empleado en nuestros registros. Por favor verifica o contacta al administrador.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error en la solicitud AJAX:', status, error);
                            $('#registerAlert').html(`
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    Error al conectar con el servidor. Intente nuevamente.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `);
                        },
                        complete: function() {
                            $('#nextBtn').prop('disabled', false).text('Verificar Código');
                        }
                    });
                }
            });
            
            $('#prevBtn').click(function() {
                if (currentStep > 1) {
                    currentStep--;
                    showStep(currentStep);
                }
            });
            
            $('#submitBtn').click(function() {
                const registerForm = $('#registerForm')[0];
                
                if (registerForm.checkValidity() === false) {
                    registerForm.classList.add('was-validated');
                    return;
                }
                
                if ($('#password').val() !== $('#confirmPassword').val()) {
                    $('#registerAlert').html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            Las contraseñas no coinciden.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `);
                    return;
                }
                
                $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registrando...');
                
                const userData = {
                    codigo_empleado: $('#codigo_validado').val(),
                    nombre: $('#nombre').val(),
                    usuario: $('#usuario').val(),
                    email: $('#email').val(),
                    password: $('#password').val(),
                    rol: 'asociado'
                };
                
                $.ajax({
                    url: 'register.php',
                    type: 'POST',
                    data: userData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: '¡Registro exitoso!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                $('#registerModal').modal('hide');
                                resetRegisterForm();
                            });
                        } else {
                            $('#registerAlert').html(`
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    ${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `);
                        }
                    },
                    error: function() {
                        $('#registerAlert').html(`
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error al registrar. Intente nuevamente.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `);
                    },
                    complete: function() {
                        $('#submitBtn').prop('disabled', false).text('Registrarse');
                    }
                });
            });
            
            function resetRegisterForm() {
                currentStep = 1;
                showStep(currentStep);
                $('#registerForm')[0].reset();
                $('#registerForm')[0].classList.remove('was-validated');
                $('#codigo_empleado').val('');
                $('#registerAlert').empty();
            }
            
            $('#registerModal').on('hidden.bs.modal', function() {
                resetRegisterForm();
            });
        });
    </script>
</body>
</html>