<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Categorías</title>
    <style>
        /* Mismos estilos básicos que antes */
        body { font-family: sans-serif; margin: 2em; }
        table { width: 100%; border-collapse: collapse; margin-top: 1em; }
        th, td { border: 1px solid #ccc; padding: 0.8em; text-align: left; }
        th { background-color: #f4f4f4; }
        .create-link {
            display: inline-block;
            padding: 0.7em 1.2em;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    @if (session('success'))
        <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 1em; border-radius: 5px; margin-bottom: 1em;">
            {{ session('success') }}
        </div>
    @endif
    <h1>Lista de Categorías</h1>

    <a href="{{ route('categories.create') }}" class="create-link">
        Crear Nueva Categoría
    </a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td>{{ $category->id }}</td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->description }}</td>
                    <td>
                        <a href="{{ route('categories.edit', $category->id) }}" style="text-decoration: none; color: #007bff; margin-right: 10px;">
                            Editar
                        </a>

                        <form action="{{ route('categories.destroy', $category->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta categoría?');">
                            @csrf
                            @method('DELETE')

                            <button type="submit" style="color: red; background: none; border: none; padding: 0; cursor: pointer;">
                                Borrar
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>