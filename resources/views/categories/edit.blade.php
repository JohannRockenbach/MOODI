<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Categoría</title>
    <style>
        /* Estilos muy básicos para que se vea ordenado */
        body { font-family: sans-serif; margin: 2em; }
        form { display: flex; flex-direction: column; max-width: 400px; }
        label { margin-top: 1em; }
        input, textarea { padding: 0.5em; margin-top: 0.5em; }
        button { margin-top: 1.5em; padding: 0.7em; cursor: pointer; }
    </style>
</head>
<body>
<h1>Editar Categoría: {{ $category->name }}</h1>    

  <form action="{{ route('categories.update', $category->id) }}" method="POST">
        @csrf @method('PUT')
       <div>
            <label for="name">Nombre:</label>
            <input type="text" id="name" name="name" value="{{ $category->name }}" required>
        </div>

        <div>
            <label for="description">Descripción:</label>
            <textarea id="description" name="description">{{ $category->description }}</textarea>
        </div>

        <button type="submit">Actualizar Categoría</button>
    </form>
</body>
</html>