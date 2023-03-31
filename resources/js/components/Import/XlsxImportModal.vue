<template>
    <v-dialog
        v-model="show"
        min-height="350"
        max-width="700">
        <template
            v-slot:activator="{ on, attrs }">
            <v-btn
                v-bind="$attrs"
                v-on="on"
                class="mx-2"
                plain
            >
                <slot/>
            </v-btn>
        </template>
        <v-card
            class="mx-auto"
        >
            <v-card-title
                class="text-h5">
                Импорт
                файлов
                Excel
            </v-card-title>
            <v-card-text>
                <v-file-input
                    color="primary"
                    counter
                    label="Выберите файлы для импорта"
                    multiple
                    placeholder="Импорт"
                    prepend-icon="mdi-paperclip"
                    outlined
                    show-size
                    accept=".xlsx"
                    v-model="files"
                    @change="onFileChange"
                >
                    <template
                        v-slot:selection="{ index, text }">
                        <v-chip
                            v-if="index < 2"
                            label
                            small
                        >
                            {{
                                text
                            }}
                        </v-chip>

                        <span
                            v-else-if="index === 2"
                            class="text-overline grey--text text--darken-3 mx-2"
                        >
                                +{{
                               files.length - 2
                            }} файл(ов)
                        </span>
                    </template>
                </v-file-input>
            </v-card-text>

            <v-card-actions>
                <v-btn plain @click="cancel()">Отмена</v-btn>
                <v-spacer />
                <v-btn
                    :disabled='isDisabled'
                    @click="startImport"
                    type="submit"
                    color="primary"
                >
                    Импорт
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios
    from "axios";
import {SweetAlert} from "../../helpers/SweetAlert";
export default {
    name: "XlsxImportModal",
    components: {},
    props: {},
    data: () => ({
        show: false,
        files: [],
        readers: [],
        imported: false,
    }),
    computed: {
        isDisabled: function(){
            return this.imported;
        },
    },
    mounted() {
    },
    methods: {
        onFileChange(e) {
            //console.log(this.files);
        },
        clearFile() {
            if (this.files !== null) {
                this.files = null;
            }
        },
        cancel(){
            this.show = false
            this.clearFile();
            this.$emit('close');
        },
        blockImportButton(status) {
            this.imported = status;
        },
        startImport(){
            const config = {
                headers: {
                    'content-type': 'multipart/form-data',
                }
            }

            // form data
            let formData = new FormData();
            this.files.forEach((file) => {
                formData.append('file', file);
            });

            this.blockImportButton(true);
            let object = this;
            // send upload request
            axios.post('/import', formData, config)
                .then(function (response) {
                    object.cancel();
                    object.blockImportButton(false);
                    if (response.data.success !== undefined) {
                        SweetAlert.success('Импорт выполнен успешно', response.data.success)
                        object.$emit('change');
                    }
                    if (response.data.error !== undefined) {
                        SweetAlert.error('Ошибка', response.data.error, 0);
                    }
                });
        },
    },
}
</script>
