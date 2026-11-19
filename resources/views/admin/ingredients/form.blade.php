<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name" class="label">Nama Bahan Baku</label>
        <input type="text" name="name" id="name" value="{{ old('name', $ingredient->name ?? '') }}" class="input @error('name') border-red-400 @enderror" required>
        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="sku" class="label">Kode (opsional)</label>
        <input type="text" name="sku" id="sku" value="{{ old('sku', $ingredient->sku ?? '') }}" class="input @error('sku') border-red-400 @enderror">
        @error('sku')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="description" class="label">Catatan (opsional)</label>
        <textarea name="description" id="description" rows="3" class="input @error('description') border-red-400 @enderror">{{ old('description', $ingredient->description ?? '') }}</textarea>
        @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $ingredient->is_active ?? true)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="text-sm text-gray-700">Aktif</span>
        </label>
    </div>
</div>

<div class="mt-6 flex items-center gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('admin.ingredients.index') }}" class="btn btn-secondary">Batal</a>
</div>