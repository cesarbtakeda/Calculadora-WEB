        let materiasAdicionadas = 1;

        function showFeedback(message) {
            const msg = document.getElementById('msg');
            msg.textContent = message;
            msg.classList.add('visible');
            setTimeout(() => msg.classList.remove('visible'), 3000);
        }

        function validateTextInput(input) {
            input.value = input.value.replace(/[^a-zA-Z\s]/g, '');
        }

        function validateNumberInput(input) {
            let valueStr = input.value.replace(',', '.');
            valueStr = valueStr.replace(/[^0-9.]/g, '');
            const parts = valueStr.split('.');
            if (parts.length > 2) {
                valueStr = parts[0] + '.' + parts.slice(1).join('');
            }
            if (parts[1] && parts[1].length > 2) {
                valueStr = parts[0] + '.' + parts[1].slice(0, 2);
            }
            input.value = valueStr;

            let value = parseFloat(valueStr);
            if (isNaN(value) || value < 0) {
                input.value = '';
            } else if (value > 10) {
                input.value = '10';
            } else if (value > 9.99 && value < 10) {
                input.value = '9.99';
            }
        }

        function adicionarMateria() {
            if (materiasAdicionadas >= 8) {
                showFeedback("Limite de 8 matérias atingido.");
                return;
            }
            materiasAdicionadas++;

            const container = document.getElementById('materiasContainer');
            const novaMateria = document.createElement('div');
            novaMateria.className = 'materia visible';
            novaMateria.id = `materia${materiasAdicionadas}`;
            novaMateria.innerHTML = `
                <h2>Matéria ${materiasAdicionadas}</h2>
                <div class="materia-nome-container">
                    <input type="text" name="materia${materiasAdicionadas}_nome" placeholder="Nome da matéria" required aria-label="Nome da matéria ${materiasAdicionadas}" oninput="validateTextInput(this)">
                </div>
                <div class="notas">
                    <input type="number" name="materia${materiasAdicionadas}_nota1" placeholder="AC1" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AC1">
                    <input type="number" name="materia${materiasAdicionadas}_nota2" placeholder="AC2" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AC2">
                    <input type="number" name="materia${materiasAdicionadas}_nota3" placeholder="PA" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota PA">
                    <input type="number" name="materia${materiasAdicionadas}_nota4" placeholder="AG" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AG">
                    <input type="number" name="materia${materiasAdicionadas}_nota5" placeholder="AS" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" aria-label="Nota AS">
                    <input type="text" name="materia${materiasAdicionadas}_media" class="media" placeholder="Média" readonly aria-label="Média da matéria ${materiasAdicionadas}">
                </div>
            `;
            container.appendChild(novaMateria);
            showFeedback(`Matéria ${materiasAdicionadas} adicionada com sucesso!`);
        }

        function removerMateria() {
            if (materiasAdicionadas <= 1) {
                showFeedback("Não há mais matérias para remover.");
                return;
            }
            const container = document.getElementById('materiasContainer');
            const ultimaMateria = document.getElementById(`materia${materiasAdicionadas}`);
            if (ultimaMateria) {
                container.removeChild(ultimaMateria);
                materiasAdicionadas--;
                showFeedback(`Matéria ${materiasAdicionadas + 1} removida.`);
                calcularMediaGeral();
            }
        }

        function calcularMedia(input) {
            const notasDiv = input.closest(".notas");
            const inputs = notasDiv.querySelectorAll('input[type="number"]');
            const pesos = [1.5, 3, 4.5, 1];
            let notas = Array.from(inputs).slice(0, 4).map(i => parseFloat(i.value) || 0);
            const notaAS = parseFloat(inputs[4].value) || 0;

            let melhorIndex = -1;
            let melhorGanho = 0;
            for (let i = 0; i < 4; i++) {
                const ganho = (notaAS * pesos[i]) - (notas[i] * pesos[i]);
                if (ganho > melhorGanho) {
                    melhorGanho = ganho;
                    melhorIndex = i;
                }
            }

            if (melhorIndex >= 0) notas[melhorIndex] = notaAS;

            const somaPonderada = notas.reduce((sum, nota, i) => sum + nota * pesos[i], 0);
            const somaPesos = pesos.reduce((sum, peso) => sum + peso, 0);
            const media = (somaPonderada / somaPesos).toFixed(2);
            notasDiv.querySelector(".media").value = media;

            calcularMediaGeral();
        }

        function calcularMediaGeral() {
            const medias = document.querySelectorAll(".materia.visible .media");
            const soma = Array.from(medias).reduce((sum, el) => sum + (parseFloat(el.value) || 0), 0);
            const mediaGeral = medias.length > 0 ? (soma / medias.length).toFixed(2) : "-";
            document.getElementById("mediaGeral").textContent = mediaGeral;
        }

        function fecharPopup() {
            document.getElementById("popupAd").style.display = "none";
        }

        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        // Adicionar event listener para o formulário
        document.getElementById('formNotas').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!checkRateLimit()) return;
            
            // Sanitizar todas as entradas
            const inputs = document.querySelectorAll('input[type="text"], input[type="number"]');
            inputs.forEach(input => {
                input.value = sanitizeInput(input.value);
            });
            
            // Verificar token CSRF
            const formToken = document.getElementById('csrf_token').value;
            if (!formToken) {
                showFeedback('Erro de segurança. Recarregue a página.');
                return;
            }
            
            // Aqui você pode adicionar o código para enviar os dados
            showFeedback('Cálculos realizados com sucesso!');
        });
