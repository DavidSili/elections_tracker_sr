const addListButton = document.getElementById('add-list');

if (addListButton) {
  addListButton.addEventListener('click', () => {
    const body = document.getElementById('list-rows');
    const row = body.lastElementChild.cloneNode(true);
    const index = body.children.length;

    row.querySelectorAll('input').forEach((input) => {
      input.name = input.name.replace(/list\[\d+\]/, `list[${index}]`);

      if (input.type === 'checkbox') {
        input.checked = false;
      } else if (input.type === 'color') {
        input.value = '#64748b';
      } else if (input.type === 'number') {
        input.value = '250';
      } else {
        input.value = '';
      }
    });

    body.append(row);
    row.querySelector('input').focus();
  });
}
