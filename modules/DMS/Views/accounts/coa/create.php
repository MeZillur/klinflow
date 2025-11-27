<h1 class="text-xl font-semibold mb-4">New Account</h1>
<form method="post" action="#">
  <div class="grid md:grid-cols-3 gap-3">
    <label>Code <input name="code" class="input"></label>
    <label>Name <input name="name" class="input"></label>
    <label>Type
      <select name="type" class="input">
        <option>asset</option><option>liability</option><option>equity</option>
        <option>income</option><option>expense</option>
      </select>
    </label>
  </div>
  <button class="btn btn--primary mt-4" disabled>Save (stub)</button>
</form>