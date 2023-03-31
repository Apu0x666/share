<template>
    <g-layout>
        <v-card class="card-100 ml-14">
            <v-card-title>
                <div>Почтовые сервера</div>
                <v-spacer></v-spacer>
                <v-dialog v-model="dialog" max-width="1000px">
                    <template v-slot:activator="{ on }">
                        <v-btn color="primary" dark class="mb-2" v-on="on" @click="addNew">Добавить почтовый сервер</v-btn>
                    </template>
                    <v-card>
                        <v-card-title>
                            <span class="headline">{{ formTitle }}</span>
                            <v-spacer />
                            <v-btn color="red" class="white--text" @click="deleteItem">Удалить</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <v-form ref="addMail" @submit.prevent="submit">
                                <v-form-base :model="formModel" :schema="formSchema" @input="formChange"/>
                                <v-card-actions>
                                    <v-btn plain @click="close">Отмена</v-btn>
                                    <v-spacer />
                                    <v-btn type="submit" color="primary" :disabled="submitDisabled">Сохранить</v-btn>
                                </v-card-actions>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </v-dialog>
            </v-card-title>
            <v-card-text>
                <div>
                    <v-data-table
                        :headers="headersWithControls"
                        :items="mailServers"
                        :server-items-length="count"
                        :options.sync="tablePageSort"
                        :loading="waitingIndicator"
                        :footer-props="{'items-per-page-options': [15,25,50,100,250,500]}"
                    >
                        <template v-for="header in headersWithControls" v-slot:[`header.${header.value}`]>
                            <div style="min-height: 80px;">
                                <div >{{header.text}}</div>
                                <div v-if="header.dictionary" @click.stop.prevent>
                                    <v-autocomplete
                                        :items="getDictionary(header.dictionary)"
                                        v-model="filter[header.value]"
                                        clearable multiple
                                    />
                                </div>
                            </div>
                        </template>
                        <template v-for="header in headersWithControls" v-slot:[`item.${header.value}`]="{item}">
                            <template v-if="header.value === 'controls_edit'">
                                <td class="justify-center">
                                    <v-icon
                                        small
                                        class="mr-2"
                                        @click="editItem(item.id)"
                                    >
                                        mdi-pencil
                                    </v-icon>
                                </td>
                            </template>
                            <template v-else>
                                {{itemValue(item, header)}}
                            </template>
                        </template>
                        
                        <template v-slot:no-data>
                            <v-btn color="primary" @click="initialize">Обновить</v-btn>
                        </template>
                    </v-data-table>
                </div>
            </v-card-text>
        </v-card>
    </g-layout>
</template>

<script>

/*
* Миграцию для создания таблицы с настройками почтового сервера
* Страница для заполнения
*
*
*   Название
    сервер
    порт
    протокол
    адрес отправителя
    адреса получателя (много адресов)
    тема письма
*
* */


import {mapState} from "vuex";
import {Inertia} from "@inertiajs/inertia";
import axios
    from "axios";
import {SweetAlert} from "../helpers/SweetAlert";

const REQUIRED = (v) => !!v || 'обязательно для заполнения'

export default {
    name: "Home",
    components: {},
    props: {
        data: {
            type: Object,
            default: () => ({})
        },
        'headers': {}
    },
    data: () => ({
        load: false,
        isNew: false,
        dialog: false,
        editedIndex: -1,
        requestStarted: false,
        item: [],
        editedItem: {
            name: '',
            server: '',
            protocol: '',
            port: '',
            from: '',
            password: '',
            to: '',
            theme: '',
        },
        defaultItem: {
            name: '',
            server: '',
            protocol: '',
            port: '',
            password: '',
            from: '',
            to: '',
            theme: '',
        },
        filter: {
            server_id: [],
            type_id: []
        },
        tablePageSort: {"page":1,"itemsPerPage":100,"sortBy":["name"],"sortDesc":[false],"sortAsc":[true],"groupBy":[],"groupDesc":[],"mustSort":false,"multiSort":false},
        mailServers: [],
    }),
    
    computed: {
        ...mapState('dictionary', ['dictionary', 'dictionaryLoading']),
        count(){
            return this.data?.count ?? -1
        },
        submitDisabled(){
            return this.requestStarted
        },
        formModel(){
            return {
                ...this.isNew ? this.defaultItem : this.editedItem
            }
        },
        formTitle () {
            return this.editedIndex === -1 ? 'Добавить почтовый сервер' : 'Редактирование почтового сервера'
        },
        headersWithControls(){
            return [{value: 'controls_edit', sortable: false},...this.headers]
        },
        formSchema() {
            return {
                name: {
                    type:'text',
                    label:'Название',
                    col: 12,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                protocol: {
                    type:'text',
                    label:'Протокол',
                    col: 5,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                server: {
                    type:'text',
                    label:'Сервер',
                    col: 5,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                port: {
                    type:'text',
                    label:'Порт',
                    col: 2,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                password: {
                    type:'password',
                    label:'Пароль',
                    col: 12,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                theme: {
                    type:'text',
                    label:'Тема письма',
                    col: 12,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                from: {
                    type:'text',
                    label:'Отправитель',
                    col: 12,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                to: {
                    type: 'textarea',
                    label: 'Получатели (через запятую)',
                    rules: [REQUIRED],
                    col: 12
                },
            }
        },
        waitingIndicator() {
            return (this.load === true) ? 'loading' : false;
        }
    },
    
    mounted() {
        this.initialize()
        
        if(this.isNew){
            this.editedItem = {
                ...this.defaultItem
            }
        }
    },
    
    watch: {
        tablePageSort(){
            this.loadData()
        },
        data(){
            this.initialize()
        },
        filter: {
            deep: true,
            handler() {
                this.loadData()
            }
        },
        load(enable) {
            return (enable) ? 'loading' : '';
        }
    },
    
    methods: {
        initialize () {
            this.mailServers = this.data?.items ?? [];
            this.load = false;
        },

        itemValue(item, header){
            return  item[header.value];
        },
        
        editItem (id) {
            this.isNew = false;
            const indexes = this.mailServers.map(el => el.id);
            this.editedIndex = indexes.indexOf(id);
            this.editedItem = Object.assign({}, this.mailServers[this.editedIndex]);
            this.dialog = true
        },
        
        addNew () {
            this.isNew = true;
            this.editedItem = Object.assign({}, this.defaultItem)
        },
        
        close () {
            this.isNew = true;
            this.dialog = false;
        },
        
        reload(){
            this.load = true;
            this.loadData().then(() => {
                this.initialize();
            });
        },
        
        async loadData() {
            this.load = true;
            Inertia.reload({
                only: ['data'],
                method: 'post',
                data: {
                    tablePageSort: this.tablePageSort,
                    filter: this.filter,
                }
            })
        },
        
        formChange(e){
            this.editedItem[e.key] = e.value
        },
        
        async submit(){
            this.requestStarted = true;
            this.load = true;
            await axios.post('/mailServersSave', {...this.editedItem}).then((response) => {
                this.requestStarted = false;
                if (response.data.success !== undefined) {
                    this.isNew = true;
                    this.close();
                    this.reload();
                    SweetAlert.toast(response.data.success);
                }
                if (response.data.error !== undefined) {
                    this.load = false;
                    SweetAlert.toast(response.data.error, 'error');
                }
            });
        },
        
        async deleteItem(){
            let item = this.mailServers[this.editedIndex];
            let confirm = await SweetAlert.confirm('Вы уверены?')
            if(confirm){
                this.load = true;
                let res = await axios.delete('/mail_servers_resource/'+item.id);
                if(res.data.success){
                    this.show = false
                    SweetAlert.toast(res.data.success);
                } else {
                    SweetAlert.toast(res.data.error, 'error');
                    console.error(res?.data?.trace);
                }
                this.reload();
                this.close();
            }
        }
    }
}
</script>

<style>
.v-data-table-header th[role="columnheader"]:not(.sortable) {
    position: relative;
}
</style>